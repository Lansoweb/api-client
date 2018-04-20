<?php

namespace Los\ApiClient;

use Los\ApiClient\Exception;
use Los\ApiClient\HttpClient\HttpClientInterface;
use Los\ApiClient\Resource\ApiResource;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

interface ApiClientInterface
{
    /**
     * @return UriInterface
     */
    public function getRootUrl() : UriInterface;

    /**
     * @param string|UriInterface $rootUrl
     * @return ApiClientInterface
     */
    public function withRootUrl($rootUrl) : ApiClientInterface;

    /**
     * @param string $name
     * @return array|string[]
     */
    public function getHeader(string $name);

    /**
     * @param string $name
     * @param string|string[] $value
     * @return ApiClientInterface
     */
    public function withHeader($name, $value) : ApiClientInterface;

    /**
     * @param string|UriInterface $uri
     * @param array $options
     * @return ApiResource
     * @throws Exception\ClientException
     * @throws Exception\RequestException
     * @throws Exception\ServerException
     * @throws Exception\BadResponseException
     */
    public function get($uri, array $options = []);

    /**
     * @param string|UriInterface $uri
     * @param array $options
     * @return ApiResource
     * @throws Exception\BadResponseException
     * @throws Exception\ClientException
     * @throws Exception\RequestException
     * @throws Exception\ServerException
     */
    public function post($uri, array $options = []);

    /**
     * @param string|UriInterface $uri
     * @param array $options
     * @return ApiResource
     * @throws Exception\ClientException
     * @throws Exception\RequestException
     * @throws Exception\ServerException
     * @throws Exception\BadResponseException
     */
    public function patch($uri, array $options = []);

    /**
     * @param string|UriInterface $uri
     * @param array $options
     * @return ApiResource
     * @throws Exception\ClientException
     * @throws Exception\RequestException
     * @throws Exception\ServerException
     * @throws Exception\BadResponseException
     */
    public function put($uri, array $options = []);

    /**
     * @param string|UriInterface $uri
     * @param array $options
     * @return ApiResource
     * @throws Exception\ClientException
     * @throws Exception\RequestException
     * @throws Exception\ServerException
     * @throws Exception\BadResponseException
     */
    public function delete($uri, array $options = []);

    /**
     * @param string $method
     * @param string|UriInterface $uri
     * @param array $options
     * @return ApiResource
     * @throws Exception\RequestException
     * @throws Exception\ClientException
     * @throws Exception\ServerException
     * @throws Exception\BadResponseException
     */
    public function request($method, $uri, array $options = []);

    /**
     * @param string $method
     * @param string|UriInterface $uri
     * @param array $options
     * @return RequestInterface|static
     */
    public function createRequest($method, $uri, array $options = []);


    /**
     * @param RequestInterface $request
     * @param string|null $id
     * @return RequestInterface
     */
    public function addRequestId(RequestInterface $request, string $id = null) : RequestInterface;

    /**
     * @param ResponseInterface $response
     * @param float $time
     * @return ResponseInterface
     */
    public function addResponseTime(ResponseInterface $response, float $time) : ResponseInterface;

    /**
     * @param RequestInterface $request
     * @param string|null $name
     * @return RequestInterface
     */
    public function addRequestName(RequestInterface $request, string $name = null) : RequestInterface;

    /**
     * @param RequestInterface $request
     * @param int $depth
     * @return RequestInterface
     */
    public function addRequestDepth(RequestInterface $request, int $depth = 0) : RequestInterface;

    /**
     * @param RequestInterface $request
     * @return RequestInterface
     */
    public function incrementRequestDepth(RequestInterface $request) : RequestInterface;

    /**
     * @return mixed $extra
     */
    public function getExtra();

    /**
     * @param mixed $extra
     * @return ApiClientInterface
     */
    public function setExtra($extra) : ApiClientInterface;

    /**
     * @return ResponseInterface
     */
    public function response(): ?ResponseInterface;

    /**
     * @return HttpClientInterface
     */
    public function httpClient(): HttpClientInterface;
}
