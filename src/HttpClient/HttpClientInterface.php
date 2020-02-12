<?php

declare(strict_types=1);

namespace Los\ApiClient\HttpClient;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// phpcs:disable SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
interface HttpClientInterface
{
    public function send(RequestInterface $request, array $options = []) : ResponseInterface;
}
