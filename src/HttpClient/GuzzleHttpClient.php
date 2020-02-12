<?php

declare(strict_types=1);

namespace Los\ApiClient\HttpClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\BadResponseException as GuzzleBadResponseException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class GuzzleHttpClient implements HttpClientInterface
{
    /** @var GuzzleClientInterface */
    private $client;

    public function __construct(?GuzzleClientInterface $client = null)
    {
        $this->client = $client ?: new GuzzleClient();
    }

    public function send(RequestInterface $request, array $options = []) : ResponseInterface
    {
        try {
            return $this->client->send($request, $options);
        } catch (GuzzleBadResponseException $e) {
            $response = $e->getResponse();
            if (! $response) {
                $response = new Response(500, [], $e->getMessage());
            }

            return $response;
        }
    }
}
