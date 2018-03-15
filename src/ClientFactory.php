<?php
namespace Los\ApiClient;

use Psr\Container\ContainerInterface;

class ClientFactory
{

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return Client
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName = null, array $options = null)
    {
        $config = $container->get('config');

        $clientConfig = $config['los']['api-client'] ?? [];

        return new Client($clientConfig['root_url'] ?? [], null, $clientConfig);
    }
}
