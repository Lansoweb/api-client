<?php

declare(strict_types=1);

namespace Los\ApiClient\Resource;

use InvalidArgumentException;
use Los\ApiClient\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Link\LinkInterface;
use RuntimeException;
use Throwable;
use const JSON_ERROR_CTRL_CHAR;
use const JSON_ERROR_DEPTH;
use const JSON_ERROR_NONE;
use const JSON_ERROR_STATE_MISMATCH;
use const JSON_ERROR_SYNTAX;
use const JSON_ERROR_UTF8;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_push;
use function array_reduce;
use function array_shift;
use function array_walk;
use function count;
use function function_exists;
use function in_array;
use function is_array;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function sprintf;

final class ApiResource
{
    use LinkCollection;

    /** @var bool */
    private $isErrorResource = false;

    /** @var array All data to represent. */
    private $data = [];

    /** @var ApiResource[] */
    private $embedded = [];

    /** @var ?ResponseInterface */
    private $response;

    /**
     * @param array           $data
     * @param LinkInterface[] $links
     * @param ApiResource[]   $embedded
     */
    public function __construct(
        array $data = [],
        array $links = [],
        array $embedded = [],
        ?ResponseInterface $response = null
    ) {
        $context = self::class;
        array_walk($data, function ($value, $name) use ($context) : void {
            $this->validateElementName($name, $context);
            $this->data[$name] = $value;
        });

        array_walk($embedded, function ($resource, $name) use ($context) : void {
            $this->validateElementName($name, $context);
            $this->detectCollisionWithData($name, $context);
            $this->embedded[$name] = $resource;
        });

        if (array_reduce($links, static function ($containsNonLinkItem, $link) {
            return $containsNonLinkItem || ! $link instanceof LinkInterface;
        }, false)) {
            throw new InvalidArgumentException('Non-Link item provided in $links array');
        }
        $this->links    = $links;
        $this->response = $response;
    }

    /**
     * @return ApiResource
     *
     * @throws Exception\BadResponse
     */
    public static function fromResponse(ResponseInterface $response) : self
    {
        try {
            $body = $response->getBody()->getContents();
        } catch (Throwable $e) {
            throw new Exception\BadResponse(
                sprintf(
                    'Error getting response body: %s.',
                    $e->getMessage()
                ),
                $response,
                $e
            );
        }

        if (empty($body)) {
            return static::fromData([], [], [], $response);
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception\BadResponse(
                sprintf(
                    'JSON parse error: %s.',
                    self::getLastJsonError()
                ),
                $response
            );
        }

        $links    = [];
        $embedded = [];

        $linksData    = $data['_links'] ?? [];
        $embeddedData = $data['_embedded'] ?? [];
        unset($data['_links'], $data['_embedded']);

        foreach ($linksData as $relation => $linkData) {
            $links[] = new Link($relation, $linkData['href']);
        }

        if (empty($embeddedData)) {
            return static::fromData($data, $links, [], $response);
        }

        $embeddedName = '';

        foreach ($embeddedData as $name => $list) {
            $embeddedName = $name;
            foreach ($list as $tok) {
                $embedded = static::createEmbedded($tok);
            }
        }

        if (empty($embeddedName)) {
            return static::fromData($data, $links, [], $response);
        }

        return static::fromData($data, $links, [$embeddedName => $embedded], $response);
    }

    /**
     * @param array $data
     * @param array $links
     * @param array $embedded
     *
     * @return ApiResource
     */
    public static function fromData(
        array $data,
        array $links = [],
        array $embedded = [],
        ?ResponseInterface $response = null
    ) : self {
        $resource = new self($data, $links, $embedded, $response);
        if ($response !== null && $response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
            $resource->isErrorResource = true;
        }

        return $resource;
    }

    private static function createEmbedded(array $tok) : array
    {
        $embedded = [];
        $halLinks = [];
        if (array_key_exists('_links', $tok)) {
            foreach ($tok['_links'] as $relation => $linkData) {
                $halLinks[] = new Link($relation, $linkData['href']);
            }
        }
        unset($tok['_links'], $tok['_embedded']);
        $embedded[] = new self($tok, $halLinks);

        return $embedded;
    }

    public function isErrorResource() : bool
    {
        return $this->isErrorResource;
    }

    public function response() : ?ResponseInterface
    {
        return $this->response;
    }

    public function isCollection() : bool
    {
        $page = $this->getElement('_page') ?? $this->getElement('page');

        return $page !== null;
    }

    public function countCollection() : int
    {
        $count = $this->getElement('_count') ?? $this->getElement('count');

        if ($count !== null) {
            return (int) $count;
        }

        if (empty($this->embedded)) {
            return 0;
        }

        foreach ($this->embedded as $key => $value) {
            return count($value);
        }

        return 0;
    }

    public function hasMorePages() : bool
    {
        $page = $this->getElement('_page') ?? $this->getElement('page');

        if ($page === null) {
            return false;
        }

        $pageCount = $this->getElement('_page_count') ?? $this->getElement('page_count');

        if ($pageCount === null) {
            return false;
        }

        return (int) $page < (int) $pageCount;
    }

    /**
     * @throws Exception\MissingElement
     */
    public function getTotalItems() : int
    {
        $count = $this->getElement('_total_items') ?? $this->getElement('total_items');

        if ($count === null) {
            throw new Exception\MissingElement('Total items element not found in response.');
        }

        return (int) $count;
    }

    /**
     * @return mixed
     *
     * @throws Exception\MissingElement
     */
    public function getFirstResource(string $name)
    {
        $element = $this->getElement($name);

        if ($element === null) {
            throw new Exception\MissingElement("Element with name '" . $name . "' not found in response.");
        }

        if (! is_array($element) || empty($element)) {
            return $element;
        }

        return $element[0];
    }

    private static function getLastJsonError() : string
    {
        if (function_exists('json_last_error_msg')) {
            return json_last_error_msg();
        }

        static $errors = [
            JSON_ERROR_NONE           => null,
            JSON_ERROR_DEPTH          => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR      => 'Unexpected control character found',
            JSON_ERROR_SYNTAX         => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8           => 'Malformed UTF-8 characters, possibly incorrectly encoded',
        ];

        $error = json_last_error();

        return array_key_exists($error, $errors)
            ? $errors[$error]
            : sprintf('Unknown error (%s)', $error);
    }

    /**
     * Retrieve a named element from the resource.
     *
     * If the element does not exist, but a corresponding embedded resource
     * is present, the embedded resource will be returned.
     *
     * If the element does not exist at all, a null value is returned.
     *
     * @return mixed
     *
     * @throws InvalidArgumentException if $name is empty.
     * @throws InvalidArgumentException if $name is a reserved keyword.
     */
    public function getElement(string $name)
    {
        $this->validateElementName($name, __METHOD__);

        if (! isset($this->data[$name]) && ! isset($this->embedded[$name])) {
            return null;
        }

        if (isset($this->embedded[$name])) {
            return $this->embedded[$name];
        }

        return $this->data[$name];
    }

    public function getResource(int $index) : ?ApiResource
    {
        if ($index >= $this->countCollection()) {
            throw new Exception\InvalidArgument('The collection has fewer elements than requested');
        }

        foreach ($this->embedded as $key => $value) {
            if (! is_array($value) || count($value) < $index) {
                throw new Exception\InvalidArgument('The collection has fewer elements than requested');
            }

            return $value[$index];
        }

        return null;
    }

    /**
     * Retrieve all elements of the resource.
     *
     * Returned as a set of key/value pairs. Embedded resources are mixed
     * in as `ApiResource` instances under the associated key.
     */
    public function getElements() : array
    {
        return array_merge($this->data, $this->embedded);
    }

    public function getData() : array
    {
        return $this->data;
    }

    /**
     * Return an instance including the named element.
     *
     * If the value is another resource, proxies to embed().
     *
     * If the $name existed in the original instance, it will be overwritten
     * by $value in the returned instance.
     *
     * @param mixed $value
     *
     * @throws InvalidArgumentException if $name is empty.
     * @throws InvalidArgumentException if $name is a reserved keyword.
     * @throws RuntimeException if $name is already in use for an embedded
     *     resource.
     */
    public function withElement(string $name, $value) : ApiResource
    {
        $this->validateElementName($name, __METHOD__);

        if (! empty($value)
            && ($value instanceof self || $this->isResourceCollection($value))
        ) {
            return $this->embed($name, $value);
        }

        $this->detectCollisionWithEmbeddedResource($name, __METHOD__);

        $new              = clone $this;
        $new->data[$name] = $value;

        return $new;
    }

    /**
     * Return an instance removing the named element or embedded resource.
     *
     * @throws InvalidArgumentException if $name is empty.
     * @throws InvalidArgumentException if $name is a reserved keyword.
     */
    public function withoutElement(string $name) : ApiResource
    {
        $this->validateElementName($name, __METHOD__);

        if (isset($this->data[$name])) {
            $new = clone $this;
            unset($new->data[$name]);

            return $new;
        }

        if (isset($this->embedded[$name])) {
            $new = clone $this;
            unset($new->embedded[$name]);

            return $new;
        }

        return $this;
    }

    /**
     * Return an instance containing the provided elements.
     *
     * If any given element exists, either as top-level data or as an embedded
     * resource, it will be replaced. Otherwise, the new elements are added to
     * the resource returned.
     */
    public function withElements(array $elements) : ApiResource
    {
        $resource = $this;
        foreach ($elements as $name => $value) {
            $resource = $resource->withElement($name, $value);
        }

        return $resource;
    }

    /**
     * @param ApiResource|ApiResource[] $resource
     * @param bool                      $forceCollection Whether or not a single resource or an
     *                          array containing a single resource should be represented as an array of
     *                          resources during representation.
     */
    public function embed(string $name, $resource, bool $forceCollection = false) : ApiResource
    {
        $this->validateElementName($name, __METHOD__);
        $this->detectCollisionWithData($name, __METHOD__);
        if (! $resource instanceof self && ! $this->isResourceCollection($resource)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects a %s instance or array of %s instances',
                __METHOD__,
                self::class,
                self::class
            ));
        }
        $new                  = clone $this;
        $new->embedded[$name] = $this->aggregateEmbeddedResource($name, $resource, $forceCollection);

        return $new;
    }

    public function toArray() : array
    {
        $resource = $this->data;

        $links = $this->serializeLinks();
        if (! empty($links)) {
            $resource['_links'] = $links;
        }

        $embedded = $this->serializeEmbeddedResources();
        if (! empty($embedded)) {
            $resource['_embedded'] = $embedded;
        }

        return $resource;
    }

    public function jsonSerialize() : array
    {
        return $this->toArray();
    }

    private function validateElementName($name, string $context) : void
    {
        if ($name === '0' || $name === 0) {
            return;
        }

        if (empty($name)) {
            throw new InvalidArgumentException(sprintf(
                '$name provided to %s cannot be empty',
                $context
            ));
        }
        if (in_array($name, ['_links', '_embedded'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Error calling %s: %s is not a reserved element $name and cannot be retrieved',
                $context,
                $name
            ));
        }
    }

    private function detectCollisionWithData(string $name, string $context) : void
    {
        if (isset($this->data[$name])) {
            throw new RuntimeException(sprintf(
                'Collision detected in %s; attempt to embed resource matching element name "%s"',
                $context,
                $name
            ));
        }
    }

    private function detectCollisionWithEmbeddedResource(string $name, string $context) : void
    {
        if (isset($this->embedded[$name])) {
            throw new RuntimeException(sprintf(
                'Collision detected in %s; attempt to add element matching resource name "%s"',
                $context,
                $name
            ));
        }
    }

    /**
     * Determine how to aggregate an embedded resource.
     *
     * If no embedded resource exists with the given name, returns it verbatim.
     *
     * If another does, it compares the new resource with the old, raising an
     * exception if they differ in structure, and returning an array containing
     * both if they do not.
     *
     * If another does as an array, it compares the new resource with the
     * structure of the first element; if they are comparable, then it appends
     * the new one to the list.
     *
     * @param mixed $resource
     *
     * @return ApiResource|ApiResource[]
     */
    private function aggregateEmbeddedResource(string $name, $resource, bool $forceCollection)
    {
        if (! isset($this->embedded[$name])) {
            return $forceCollection ? [$resource] : $resource;
        }

        // $resource is an collection; existing individual or collection resource exists
        if (is_array($resource)) {
            return $this->aggregateEmbeddedCollection($name, $resource);
        }

        // $resource is a ApiResource; existing resource is also a ApiResource
        if ($this->embedded[$name] instanceof self) {
            return [$this->embedded[$name], $resource];
        }

        $collection = $this->embedded[$name];
        /** @noinspection PhpParamsInspection */
        array_push($collection, $resource);

        return $collection;
    }

    private function aggregateEmbeddedCollection(string $name, array $collection) : array
    {
        return [$this->embedded[$name]] + $collection;
    }

    // phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
    private function isResourceCollection($value) : bool
    {
        if (! is_array($value)) {
            return false;
        }

        return array_reduce($value, static function ($isResource, $item) {
            return $isResource && $item instanceof self;
        }, true);
    }

    private function serializeLinks() : array
    {
        $relations = array_reduce($this->links, static function (array $byRelation, LinkInterface $link) {
            $representation = array_merge($link->getAttributes(), [
                'href' => $link->getHref(),
            ]);
            if ($link->isTemplated()) {
                $representation['templated'] = true;
            }

            $linkRels = $link->getRels();
            array_walk($linkRels, static function ($rel) use (&$byRelation, $representation) : void {
                $forceCollection = array_key_exists(Link::AS_COLLECTION, $representation)
                    ? (bool) $representation[Link::AS_COLLECTION]
                    : false;
                unset($representation[Link::AS_COLLECTION]);

                if (isset($byRelation[$rel])) {
                    $byRelation[$rel][] = $representation;
                } else {
                    $byRelation[$rel] = [$representation];
                }

                // If we're forcing a collection, and the current relation only
                // has one item, mark the relation to force a collection
                if (count($byRelation[$rel]) === 1 && $forceCollection) {
                    $byRelation[$rel][Link::AS_COLLECTION] = true;
                }

                // If we have more than one link for the relation, and the
                // marker for forcing a collection is present, remove the
                // marker; it's redundant. Check for a count greater than 2,
                // as the marker itself will affect the count!
                if (2 >= count($byRelation[$rel]) || ! isset($byRelation[$rel][Link::AS_COLLECTION])) {
                    return;
                }

                unset($byRelation[$rel][Link::AS_COLLECTION]);
            });

            return $byRelation;
        }, []);

        array_walk($relations, static function ($links, $key) use (&$relations) : void {
            if (isset($relations[$key][Link::AS_COLLECTION])) {
                // If forcing a collection, do nothing to the links, but DO
                // remove the marker indicating a collection should be
                // returned.
                unset($relations[$key][Link::AS_COLLECTION]);

                return;
            }

            $relations[$key] = count($links) === 1 ? array_shift($links) : $links;
        });

        return $relations;
    }

    private function serializeEmbeddedResources() : array
    {
        $embedded = [];
        array_walk($this->embedded, static function ($resource, $name) use (&$embedded) : void {
            $embedded[$name] = $resource instanceof self
                ? $resource->toArray()
                : array_map(static function (ApiResource $item) {
                    return $item->toArray();
                }, $resource);
        });

        return $embedded;
    }
}
