<?php

namespace Los\ApiClient\Exception;

use Psr\Http\Message\RequestInterface;

class AbstractException extends \Exception implements ExceptionInterface
{
    public static function fromRequest(RequestInterface $request, $previous = null, $message = null)
    {
        if (! $message) {
            $message = 'Exception thrown by the http client while sending request.';

            /** @var \Throwable $previous */
            if ($previous) {
                $message = sprintf(
                    'Exception thrown by the http client while sending request: %s.',
                    $previous->getMessage()
                );
            }
        }

        return new self($message, $request, $previous);
    }
}
