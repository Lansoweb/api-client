<?php

declare(strict_types=1);

namespace Los\ApiClient\Exception;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function sprintf;

class BadResponse extends Exception
{
    public function __construct(
        string $message,
        private ResponseInterface $response,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $response->getStatusCode(), $previous);
    }

    public static function create(
        ResponseInterface $response,
        ?Throwable $previous = null,
        ?string $message = null,
    ): self {
        if (! $message) {
            $code = $response->getStatusCode();

            if ($code >= 400 && $code < 500) {
                $message = 'Client error';
            } elseif ($code >= 500 && $code < 600) {
                $message = 'Server error';
            } else {
                $message = 'Unsuccessful response';
            }
        }

        $message = sprintf(
            '%s [status code] %s [reason phrase] %s.',
            $message,
            $response->getStatusCode(),
            $response->getReasonPhrase(),
        );

        return new self($message, $response, $previous);
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function isClientError(): bool
    {
        return $this->getCode() >= 400 && $this->getCode() < 500;
    }

    public function isServerError(): bool
    {
        return $this->getCode() >= 500 && $this->getCode() < 600;
    }
}
