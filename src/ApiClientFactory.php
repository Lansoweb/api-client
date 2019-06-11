<?php

declare(strict_types=1);

namespace Los\ApiClient;

use Psr\Container\ContainerInterface;

class ApiClientFactory
{
    public function __invoke(ContainerInterface $container) : ApiClientInterface
    {
        $config = $container->get('config');

        $clientConfig = $config['los']['api-client'] ?? [];

        return new ApiClient(
            $clientConfig['root_url'] ?? '',
            $clientConfig
        );
    }
}
