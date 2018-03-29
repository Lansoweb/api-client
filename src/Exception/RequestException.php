<?php

namespace Los\ApiClient\Exception;

class RequestException extends AbstractException implements ExceptionInterface
{
    public static function fromThrowable(\Throwable $previous = null) : self
    {
        return new self('', 400, $previous);
    }
}
