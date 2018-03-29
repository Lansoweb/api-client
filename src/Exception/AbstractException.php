<?php

namespace Los\ApiClient\Exception;

use Throwable;

class AbstractException extends \Exception implements ExceptionInterface
{
    /**
     * AbstractException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
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

        parent::__construct($message, $code, $previous);
    }
}
