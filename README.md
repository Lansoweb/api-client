# Api-Client

ApiClient is a php library to consume Restful APIs using Hal, like [Apigility](http://apigility.org).

## Requirements

Please, see composer.json

## Installation

```bash
php composer.phar require los/api-client
```

### Configuration
You need to configure at least the Api URI.

If using a framework that implements `container-interopt`, you can use the following configuration:

Copy the los-api-client.global.php.dist from this module to your application's config folder and make the necessary changes.

```php
'los' => [
    'api-client' => [
        'root_uri' => 'http://localhost:8000',
        'add_request_id' => true,
        'add_request_time' => true,
        'add_request_depth' => true,
        'headers' => [
            'Accept'       => 'application/hal+json',
            'Content-Type' => 'application/json',
        ],
        'query' => [
            'key' => '123',
        ],
        'request_options' => [
            'request_options' => [
                'timeout' => 10,
                'connect_timeout' => 2,
                'read_timeout' => 10,
            ],
        ],
    ],
],
```

## Usage

### Creating the client
You can use the `Los\ApiClient\ClientFactory` using the above configuration or manually:
```php
$client = new \Los\ApiClient\ApiClient('http://api.example.com');
```

### Single resource
```php
/* @var \Los\ApiClient\ApiClient $client */
$client = new \Los\ApiClient\ApiClient('http://api.example.com');

/* @var \Los\ApiClient\Resource\ApiResource $ret */
$ret = $client->get('/album/1');

// $data is an array with all data and resources (_embedded) from the response
$data = $ret->getData();
```

### Collection
```php
/* @var \Los\ApiClient\ApiClient $client */
$client = new \Los\ApiClient\ApiClient('http://api.example.com');

/* @var \Los\ApiClient\Resource\ApiResource $ret */
$ret = $client->get('/album', [ 'query' => ['year' => 2018] ]);

// $data is an array with all data and resources (_embedded) from the response
$data = $ret->getData();

// $data is an array with the first album resource from the response
$data = $ret->getFirstResource('album');

// $data is an array with the all album resources from the response
$data = $ret->getResources('album');

// $data is an array with the all resources from the response
$data = $ret->getResources();
```

### Events

The client triggers some events:
* request.pre
* request.post
* request.fail

More info about events on [zend-eventmanager](https://github.com/laminas/laminas-eventmanager).

### Request Id

The client automatically adds a X-Request-Id to each request, but only if there is no previous X-Request-Id added.

You can force a new id with:
```php
$client = $this->getServiceLocator()->get('hermes');
$client->addRequestId(); // Auto generared
$client->addRequestId('123abc');
```
