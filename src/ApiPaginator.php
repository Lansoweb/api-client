<?php

declare(strict_types=1);

namespace Los\ApiClient;

use Laminas\Paginator\Adapter\AdapterInterface;
use Los\ApiClient\Resource\ApiResource;

class ApiPaginator implements AdapterInterface
{
    private ApiClientInterface $client;
    private ?ApiResource $resource;

    public function __construct(
        ApiClient $apiClient,
        private string $url,
        private string $collectionName,
        private array $query = [],
    ) {
        $this->client = $apiClient;
    }

    /**
     * {@inheritDoc}
     *
     * @see \Laminas\Paginator\Adapter\AdapterInterface::getItems()
     */
    public function getItems($offset, $itemCountPerPage): array
    {
        if ($this->resource === null) {
            $this->resource = $this->client->get($this->url, ['query' => $this->query]);
        }

        $data = $this->resource->getElement($this->collectionName);

        if ($data === null) {
            return [];
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     *
     * @see Countable::count()
     */
    public function count($mode = null): int
    {
        if ($this->resource === null) {
            $this->resource = $this->client->get($this->url, ['query' => $this->query]);
        }

        $count = $this->resource->getElement('_total_items') ?? $this->resource->getElement('total_items');
        if ($count === null) {
            throw new Exception\MissingElement('Total items element not found in response.');
        }

        return (int) $count;
    }
}
