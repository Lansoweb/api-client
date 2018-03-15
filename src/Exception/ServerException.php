<?php

namespace Los\ApiClient\Exception;

use Psr\Http\Message\RequestInterface;

class ServerException extends AbstractException implements ExceptionInterface
{
    public static function fromRequest(RequestInterface $request, $previous = null, $message = null): self
    {
        return parent::fromRequest($request, $previous, $message);
    }
}
