<?php

namespace Los\ApiClient\Exception;

class ServerException extends AbstractException implements ExceptionInterface
{
    public static function fromThrowable(\Throwable $previous = null) : self
    {
        return new self('', 500, $previous);
    }
}
