<?php

declare(strict_types=1);

namespace Los\ApiClient;

use Los\ApiClient\HttpClient\HttpClientInterface;
use Los\ApiClient\Resource\ApiResource;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

// phpcs:disable SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface ApiClientInterface
{
    public function getRootUrl(): UriInterface;

    /** @param string|UriInterface $rootUrl */
    public function withRootUrl($rootUrl): ApiClientInterface;

    /** @return array|string[] */
    public function getHeader(string $name): array;

    public function hasHeader(string $name): bool;

    public function withoutHeader(string $name): ApiClientInterface;

    /** @param string|string[] $value */
    public function withHeader(string $name, $value): ApiClientInterface;

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\ClientError
     * @throws Exception\RequestError
     * @throws Exception\ServerError
     * @throws Exception\BadResponse
     */
    public function get($uri, array $options = []): ApiResource;

    public function getCached(string $uri, string $cacheKey, array $options = [], ?int $ttl = null): ApiResource;

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\BadResponse
     * @throws Exception\ClientError
     * @throws Exception\RequestError
     * @throws Exception\ServerError
     */
    public function post($uri, array $options = []): ApiResource;

    public function postCached(string $uri, string $cacheKey, array $options = [], ?int $ttl = null): ApiResource;

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\ClientError
     * @throws Exception\RequestError
     * @throws Exception\ServerError
     * @throws Exception\BadResponse
     */
    public function patch($uri, array $options = []): ApiResource;

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\ClientError
     * @throws Exception\RequestError
     * @throws Exception\ServerError
     * @throws Exception\BadResponse
     */
    public function put($uri, array $options = []): ApiResource;

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\ClientError
     * @throws Exception\RequestError
     * @throws Exception\ServerError
     * @throws Exception\BadResponse
     */
    public function delete($uri, array $options = []): ApiResource;

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @throws Exception\RequestError
     * @throws Exception\ClientError
     * @throws Exception\ServerError
     * @throws Exception\BadResponse
     */
    public function request(string $method, $uri, array $options = []): ApiResource;

    /**
     * @param string|UriInterface $uri
     * @param array               $options
     *
     * @return RequestInterface|static
     */
    public function createRequest(string $method, $uri, array $options = []);

    public function addRequestId(RequestInterface $request, ?string $id = null): RequestInterface;

    public function addResponseTime(ResponseInterface $response, float $time): ResponseInterface;

    public function addRequestName(RequestInterface $request, ?string $name = null): RequestInterface;

    public function addRequestDepth(RequestInterface $request, int $depth = 0): RequestInterface;

    public function incrementRequestDepth(RequestInterface $request): RequestInterface;

    /** @return mixed $extra */
    public function getExtra();

    /** @param mixed $extra */
    public function setExtra($extra): ApiClientInterface;

    public function response(): ?ResponseInterface;

    public function httpClient(): HttpClientInterface;

    public function withHttpClient(HttpClientInterface $httpClient): ApiClientInterface;

    public function clearCacheKey(string $cacheKey): void;
}
