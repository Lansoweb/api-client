<?php
namespace Los\ApiClient;

use Psr\Container\ContainerInterface;

class ApiClientFactory
{

    public function __invoke(ContainerInterface $container, $requestedName = null, array $options = null)
    {
        $config = $container->get('config');

        $clientConfig = $config['los']['api-client'] ?? [];

        return new ApiClient(
            $clientConfig['root_url'] ?? '',
            $clientConfig
        );
    }
}
