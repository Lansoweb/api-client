<?php
declare(strict_types=1);

namespace Los\ApiClient;

class ConfigProvider
{
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies() : array
    {
        return [
            'factories' => [
                ApiClient::class => ApiClientFactory::class,
            ],
        ];
    }
}
