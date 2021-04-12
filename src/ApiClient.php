<?php

declare(strict_types=1);

namespace Los\ApiClient;

use GuzzleHttp\Exception as GuzzleException;
use GuzzleHttp\Psr7 as GuzzlePsr7;
use Laminas\EventManager\EventManagerAwareTrait;
use Los\ApiClient\Exception\CacheNotSaved;
use Los\ApiClient\HttpClient\GuzzleHttpClient;
use Los\ApiClient\HttpClient\HttpClientInterface;
use Los\ApiClient\Resource\ApiResource;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

use function array_key_exists;
use function array_merge;
use function array_merge_recursive;
use function assert;
use function constant;
use function defined;
use function http_build_query;
use function implode;
use function is_array;
use function json_encode;
use function microtime;
use function sprintf;

final class ApiClient implements ApiClientInterface
{
    use EventManagerAwareTrait;

    private HttpClientInterface $httpClient;

    /** @var array  */
    private array $defaultOptions = [];

    private RequestInterface $defaultRequest;

    private bool $httpErrors = true;

    private bool $allow5xx = false;

    private string $exception5xx = Exception\BadResponse::class;

    /** @var array */
    private array $exceptionStatusCodes = [];

    private ResponseInterface $response;

    /**
     * Extra information. Provided by the client
     *
     * @var mixed
     */
    private $extra;

    /** @var array  */
    private static array $validContentTypes = [
        'application/hal+json',
        'application/json',
        'application/vnd.error+json',
    ];

    private ?CacheInterface $cache = null;

    private ?int $defaultPerItemTtl = null;

    public function __construct(
        string $rootUrl,
        array $options = [],
        ?CacheInterface $cache = null
    ) {
        $this->httpClient = new GuzzleHttpClient();

        $this->cache = $cache;

        $this->defaultOptions = $options;

        $this->defaultPerItemTtl = $options['default_ttl'] ?? null;

        $this->defaultRequest = new GuzzlePsr7\Request(
            'GET',
            $rootUrl,
            array_merge_recursive(
                [
                    'User-Agent' => static::class,
                    'Accept'     => implode(', ', self::$validContentTypes),
                ],
                $this->defaultOptions['headers'] ?? []
            )
        );
    }

    public function __clone()
    {
        $this->httpClient     = clone $this->httpClient;
        $this->defaultRequest = clone $this->defaultRequest;
    }

    public function getRootUrl(): UriInterface
    {
        return $this->defaultRequest->getUri();
    }

    /**
     * @param string|UriInterface $rootUrl
     */
    public function withRootUrl($rootUrl): ApiClientInterface
    {
        $instance = clone $this;

        $instance->defaultRequest = $instance->defaultRequest->withUri(GuzzlePsr7\uri_for($rootUrl));

        return $instance;
    }

    /**
     * @return array|string[]
     */
    public function getHeader(string $name): array
    {
        return $this->defaultRequest->getHeader($name);
    }

    public function hasHeader(string $name): bool
    {
        return $this->defaultRequest->hasHeader($name);
    }

    public function withoutHeader(string $name): ApiClientInterface
    {
        $instance                 = clone $this;
        $instance->defaultRequest = $instance->defaultRequest->withoutHeader($name);

        return $instance;
    }

    /**
     * @param string|string[] $value
     */
    public function withHeader(string $name, $value): ApiClientInterface
    {
        $instance                 = clone $this;
        $instance->defaultRequest = $instance->defaultRequest->withHeader(
            $name,
            $value
        );

        return $instance;
    }

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\ClientError
     * @throws Exception\RequestError
     * @throws Exception\ServerError
     * @throws Exception\BadResponse
     */
    public function get($uri, array $options = []): ApiResource
    {
        return $this->request('GET', $uri, $options);
    }

    public function getCached(string $uri, string $cacheKey, array $options = [], ?int $ttl = null): ApiResource
    {
        if ($ttl === null && $this->defaultPerItemTtl !== null) {
            $ttl = $this->defaultPerItemTtl;
        }

        if (! $this->cache instanceof CacheInterface) {
            throw new Exception\RuntimeError('No cache defined.');
        }

        if ($this->cache->has($cacheKey) !== false) {
            return ApiResource::fromResponse(new GuzzlePsr7\Response(200, [], $this->cache->get($cacheKey)));
        }

        $response = $this->get($uri, $options);

        $responseArray = $response->toArray();

        if (! $response->isErrorResource() && ! empty($responseArray)) {
            $cacheSaved = $this->cache->set($cacheKey, json_encode($responseArray), $ttl);

            if (! $cacheSaved) {
                throw new CacheNotSaved();
            }
        }

        return $response;
    }

    public function clearCacheKey(string $cacheKey): void
    {
        if (! $this->cache instanceof CacheInterface) {
            return;
        }

        if (! $this->cache->has($cacheKey)) {
            return;
        }

        $this->cache->delete($cacheKey);
    }

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\BadResponse
     * @throws Exception\ClientError
     * @throws Exception\RequestError
     * @throws Exception\ServerError
     */
    public function post($uri, array $options = []): ApiResource
    {
        return $this->request('POST', $uri, $options);
    }

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\ClientError
     * @throws Exception\RequestError
     * @throws Exception\ServerError
     * @throws Exception\BadResponse
     */
    public function patch($uri, array $options = []): ApiResource
    {
        return $this->request('PATCH', $uri, $options);
    }

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\ClientError
     * @throws Exception\RequestError
     * @throws Exception\ServerError
     * @throws Exception\BadResponse
     */
    public function put($uri, array $options = []): ApiResource
    {
        return $this->request('PUT', $uri, $options);
    }

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\ClientError
     * @throws Exception\RequestError
     * @throws Exception\ServerError
     * @throws Exception\BadResponse
     */
    public function delete($uri, array $options = []): ApiResource
    {
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\RequestError
     * @throws Exception\ClientError
     * @throws Exception\ServerError
     * @throws Exception\BadResponse
     */
    public function request(
        string $method,
        $uri,
        array $options = []
    ): ApiResource {
        $request = $this->createRequest($method, $uri, array_merge_recursive($this->defaultOptions, $options));

        $this->getEventManager()->trigger('request.pre', $this, [
            'request' => $request,
            'options' => $options,
        ]);

        try {
            $requestTime = microtime(true);

            $requestOptions = $options['request_options'] ?? $this->defaultOptions['request_options'] ?? [];
            $response       = $this->httpClient->send($request, $requestOptions);

            if (isset($options['add_request_time']) && $options['add_request_time'] === true) {
                $responseTime = (float) sprintf('%.2f', (microtime(true) - $requestTime) * 1000);

                $response = $this->addResponseTime($response, $responseTime);
            }
        } catch (GuzzleException\ConnectException $e) {
            $this->getEventManager()->trigger('request.fail', $this, $e);

            throw Exception\RequestError::fromThrowable($e);
        } catch (GuzzleException\ClientException $e) {
            $this->getEventManager()->trigger('request.fail', $this, $e);

            throw Exception\ClientError::fromThrowable($e);
        } catch (GuzzleException\ServerException $e) {
            $this->getEventManager()->trigger('request.fail', $this, $e);

            throw Exception\ServerError::fromThrowable($e);
        } catch (Throwable $e) {
            $this->getEventManager()->trigger('request.fail', $this, $e);

            throw new Exception\RuntimeError($e->getMessage(), 500, $e);
        }

        $this->getEventManager()->trigger('request.post', $this, [
            'request' => $request,
            'response' => $response,
            'options' => $options,
        ]);

        return $this->handleResponse($response, (bool) ($options['raw_response'] ?? false));
    }

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @return RequestInterface|static
     */
    public function createRequest(
        string $method,
        $uri,
        array $options = []
    ) {
        $request = clone $this->defaultRequest;
        assert($request instanceof RequestInterface);
        $request = $request->withMethod($method);
        $request = $request->withUri(
            self::resolveUri($request->getUri(), $uri)
        );

        return $this->applyOptions($request, $options);
    }

    /**
     * @param array $options
     */
    private function applyOptions(RequestInterface $request, array $options): RequestInterface
    {
        if (isset($options['query'])) {
            $request = $this->applyQuery($request, $options['query']);
        }

        if (isset($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        if (isset($options['body'])) {
            $request = $this->applyBody($request, $options['body']);
        }

        if (isset($options['add_request_id']) && $options['add_request_id'] === true) {
            $request = $this->addRequestId($request);
        }

        if (isset($options['add_request_depth']) && $options['add_request_depth'] === true) {
            $request = $this->incrementRequestDepth($request);
        }

        $this->httpErrors = (bool) ($options['http_errors'] ?? true);

        $this->allow5xx = (bool) ($options['allow5xx'] ?? false);

        if (array_key_exists('exception5xx', $options)) {
            $this->exception5xx = $options['exception5xx'];
        }

        $this->exceptionStatusCodes = $options['exception_status_codes'] ?? [];

        return $request;
    }

    /**
     * @param array|string $query
     */
    private function applyQuery(RequestInterface $request, $query): RequestInterface
    {
        $uri = $request->getUri();

        if (! is_array($query)) {
            $query = GuzzlePsr7\parse_query($query);
        }

        $newQuery = array_merge(
            GuzzlePsr7\parse_query($uri->getQuery()),
            $query
        );

        return $request->withUri($uri->withQuery(http_build_query($newQuery)));
    }

    /**
     * @param array|string $body
     */
    private function applyBody(RequestInterface $request, $body): RequestInterface
    {
        if (is_array($body)) {
            $body = json_encode($body);
            if (! $request->hasHeader('Content-Type')) {
                $request = $request->withHeader(
                    'Content-Type',
                    'application/json'
                );
            }
        }

        return $request->withBody(GuzzlePsr7\stream_for($body));
    }

    /**
     * @throws Exception\BadResponse
     */
    private function handleResponse(ResponseInterface $response, bool $rawResponse): ApiResource
    {
        $statusCode     = $response->getStatusCode();
        $this->response = $response;

        if ($this->httpErrors && ($statusCode < 200 || $statusCode >= 400)) {
            throw Exception\BadResponse::create($response);
        }

        if (! $this->allow5xx && $statusCode >= 500 && $statusCode <= 599) {
            throw $this->exception5xx::create($response);
        }

        if (array_key_exists($statusCode, $this->exceptionStatusCodes)) {
            throw $this->exceptionStatusCodes[$statusCode]::create($response);
        }

        if ($rawResponse) {
            return new ApiResource();
        }

        return ApiResource::fromResponse($response);
    }

    /**
     * @param string|UriInterface $rel
     *
     * @return mixed
     */
    private static function resolveUri(UriInterface $base, $rel)
    {
        static $resolver, $castRel;

        if (! $resolver) {
            $resolver = ['GuzzleHttp\Psr7\UriResolver', 'resolve'];
            $castRel  = true;
        }

        if ($castRel && ! ($rel instanceof UriInterface)) {
            $rel = new GuzzlePsr7\Uri($rel);
        }

        return $resolver($base, $rel);
    }

    public function addRequestId(RequestInterface $request, ?string $id = null): RequestInterface
    {
        if (! $request->hasHeader('X-Request-Id')) {
            return clone $request;
        }

        if ($id === null) {
            $id = defined('REQUEST_ID') ? constant('REQUEST_ID') : Uuid::uuid4();
        }

        $request = $request->withoutHeader('X-Request-Id');

        return $request->withHeader('X-Request-Id', $id);
    }

    public function addResponseTime(ResponseInterface $response, float $time): ResponseInterface
    {
        $response = $response->withoutHeader('X-Response-Time');

        return $response->withHeader('X-Response-Time', sprintf('%2.2fms', $time));
    }

    public function addRequestName(RequestInterface $request, ?string $name = null): RequestInterface
    {
        if (empty($name)) {
            return clone $request;
        }

        $request = $request->withoutHeader('X-Request-Name');

        return $request->withHeader('X-Request-Name', $name);
    }

    public function addRequestDepth(RequestInterface $request, int $depth = 0): RequestInterface
    {
        if ($request->hasHeader('X-Request-Depth')) {
            return $this->incrementRequestDepth($request);
        }

        return $request->withHeader('X-Request-Depth', $depth);
    }

    public function incrementRequestDepth(RequestInterface $request): RequestInterface
    {
        $depth = 0;

        if ($request->hasHeader('X-Request-Depth')) {
            $depth   = $request->getHeader('X-Request-Depth')[0];
            $request = $request->withoutHeader('X-Request-Depth');
        }

        $depth++;

        return $request->withHeader('X-Request-Depth', $depth);
    }

    /**
     * @return mixed $extra
     */
    public function getExtra()
    {
        return $this->extra;
    }

    /**
     * @param mixed $extra
     */
    public function setExtra($extra): ApiClientInterface
    {
        $this->extra = $extra;

        return $this;
    }

    public function response(): ?ResponseInterface
    {
        return $this->response;
    }

    public function httpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }

    public function withHttpClient(HttpClientInterface $httpClient): ApiClientInterface
    {
        $instance             = clone $this;
        $instance->httpClient = $httpClient;

        return $instance;
    }
}
