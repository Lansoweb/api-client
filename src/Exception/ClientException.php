<?php

namespace Los\ApiClient\Exception;

use Psr\Http\Message\RequestInterface;

class ClientException extends AbstractException implements ExceptionInterface
{
    /**
     * @param RequestInterface $request
     * @param null $previous
     * @param null $message
     * @return ClientException
     */
    public static function fromRequest(RequestInterface $request, $previous = null, $message = null): self
    {
        return parent::fromRequest($request, $previous, $message);
    }
}
