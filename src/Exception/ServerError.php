<?php

declare(strict_types=1);

namespace Los\ApiClient\Exception;

use Exception;
use Throwable;

class ServerError extends Exception
{
    public static function fromThrowable(?Throwable $previous = null): self
    {
        return new self('', 500, $previous);
    }
}
