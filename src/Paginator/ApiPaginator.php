<?php
namespace Los\ApiClient;

use Zend\Paginator\Adapter\AdapterInterface;

class ApiPaginator implements AdapterInterface
{
    private $client;
    private $url;
    private $query;
    private $collectionName;

    private $resource;

    public function __construct(ApiClient $apiClient, string $url, string $collectionName, array $query = [])
    {
        $this->client = $apiClient;
        $this->url = $url;
        $this->query = $query;
        $this->collectionName = $collectionName;
    }

    /**
     *
     * {@inheritDoc}
     *
     * @see \Zend\Paginator\Adapter\AdapterInterface::getItems()
     */
    public function getItems($offset, $itemCountPerPage) : array
    {
        if ($this->resource == null) {
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
     * @see Countable::count()
     */
    public function count($mode = null)
    {
        if ($this->resource == null) {
            $this->resource = $this->client->get($this->url, ['query' => $this->query]);
        }

        $count = $this->resource->getElement('_total_items') ?? $this->resource->getElement('total_items');
        if ($count === null) {
            throw new Exception\MissingElementException('Total items element not found in response.');
        }

        return (int) $count;
    }
}
