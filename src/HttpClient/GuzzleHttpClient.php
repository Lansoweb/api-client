<?php

declare(strict_types=1);

namespace Los\ApiClient\HttpClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\BadResponseException as GuzzleBadResponseException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class GuzzleHttpClient implements HttpClientInterface
{
    private GuzzleClientInterface $client;

    public function __construct(?GuzzleClientInterface $client = null)
    {
        $this->client = $client ?: new GuzzleClient();
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        try {
            return $this->client->send($request, $options);
        } catch (GuzzleBadResponseException $e) {
            return $e->getResponse();
        }
    }
}
