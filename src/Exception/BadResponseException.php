<?php

namespace Los\ApiClient\Exception;

use Psr\Http\Message\ResponseInterface;

class BadResponseException extends \Exception implements ExceptionInterface
{
    private $response;

    public function __construct(
        $message,
        ResponseInterface $response,
        $previous = null
    ) {
        $code = $response ? $response->getStatusCode() : 0;

        parent::__construct($message, $code, $previous);

        $this->response = $response;
    }

    public static function create(
        ResponseInterface $response,
        $previous = null,
        $message = null
    ) {
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
            $response->getReasonPhrase()
        );

        return new self($message, $response, $previous);
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function isClientError()
    {
        return $this->getCode() >= 400 && $this->getCode() < 500;
    }

    public function isServerError()
    {
        return $this->getCode() >= 500 && $this->getCode() < 600;
    }
}
