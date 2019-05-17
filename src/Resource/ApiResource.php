<?php

namespace Los\ApiClient\Resource;

use Los\ApiClient\Exception;
use Psr\Http\Message\ResponseInterface;
use Zend\Expressive\Hal\HalResource;

final class ApiResource extends HalResource
{
    private $isErrorResource = false;

    /**
     * @param ResponseInterface $response
     * @return ApiResource
     * @throws Exception\BadResponseException
     */
    public static function fromResponse(ResponseInterface $response) : self
    {
        try {
            $body = $response->getBody()->getContents();
        } catch (\Throwable $e) {
            throw new Exception\BadResponseException(
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

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new Exception\BadResponseException(
                sprintf(
                    'JSON parse error: %s.',
                    self::getLastJsonError()
                ),
                $response
            );
        }

        $links = [];
        $embedded = [];

        $linksData = $data['_links'] ?? [];
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
                $halLinks = [];
                if (array_key_exists('_links', $tok)) {
                    foreach ($tok['_links'] as $relation => $linkData) {
                        $halLinks[] = new Link($relation, $linkData['href']);
                    }
                }
                unset($tok['_links'], $tok['_embedded']);
                $embedded[] = new self($tok, $halLinks);
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
     * @param ResponseInterface|null $response
     * @return ApiResource
     */
    public static function fromData(
        array $data,
        array $links = [],
        array $embedded = [],
        ResponseInterface $response = null
    ) : self {
        $resource = new self($data, $links, $embedded);
        if ($response !== null && $response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
            $resource->isErrorResource = true;
        }
        return $resource;
    }

    /**
     * @return bool
     */
    public function isErrorResource(): bool
    {
        return $this->isErrorResource;
    }

    /**
     * @return bool
     */
    public function isCollection() : bool
    {
        $page = $this->getElement('_page') ?? $this->getElement('page');
        return $page !== null;
    }

    /**
     * @return bool
     */
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
     * @return int
     * @throws Exception\MissingElementException
     */
    public function getTotalItems() : int
    {
        $count = $this->getElement('_total_items') ?? $this->getElement('total_items');

        if ($count === null) {
            throw new Exception\MissingElementException('Total items element not found in response.');
        }

        return (int) $count;
    }

    /**
     * @param string $name
     * @return mixed
     * @throws Exception\MissingElementException
     */
    public function getFirstResource(string $name)
    {
        $element = $this->getElement($name);

        if ($element === null) {
            throw new Exception\MissingElementException("Element with name '$name' not found in response.");
        }

        if (! is_array($element) || empty($element)) {
            return $element;
        }

        return $element[0];
    }

    /**
     * @return string
     */
    private static function getLastJsonError()
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
}
