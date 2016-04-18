# Classy PHP SDK [![Build Status](https://travis-ci.org/classy-org/classy-php-sdk.png?branch=master)](https://travis-ci.org/classy-org/classy-php-sdk) [![codecov.io](https://codecov.io/github/classy-org/classy-php-sdk/coverage.svg?branch=master)](https://codecov.io/github/classy-org/classy-php-sdk?branch=master)

This repository contains a php HTTP client library to let your php App interact with Classy's API.

## Installation

The Classy PHP SDK can be installed with [Composer](https://getcomposer.org/):

```sh
composer require classy-org/classy-php-sdk
```

Be sure you included composer autoloader in your app:

```php
require_once '/path/to/your/project/vendor/autoload.php';
```

## Usage

### Basic example

```php
$client = new \Classy\Client([
    'client_id'     => 'your_client_id',
    'client_secret' => 'your_client_secret',
    'version'       => '2.0' // version of the API to be used
]);

$session = $client->newAppSession();

// Get information regarding the campaign #1234
$campaign = $client->get('/campaigns/1234', $session);

// Access the campaign goal: $campaign->goal

// Unpublish the campaign
$client->post('/campaign/1234/deactivate', $session);
```

### Sessions handling

Sessions have an expiration date. It is possible to refresh a session:

```php
if ($session->expired()) {
    $client->refresh($session)
}

// $session->expired() is now false.
```

Sessions are serializable, they can be saved an reused to reduce the amount of API calls:

```php
$client = new \Classy\Client([
    'client_id'     => 'your_client_id',
    'client_secret' => 'your_client_secret',
    'version'       => '2.0' // version of the API to be used
]);

// Retrieve the session from a file
$session = unserialize(file_get_contents("path/to/a/cache/file"));

// ... work with the API...

// Save the session for later
file_put_contents("path/to/a/cache/file", serialize($session));
```

### Errors handling

This client can throw two types of Exceptions:

* Classy\Exceptions\SDKException when the SDK is misused
* Classy\Exceptions\APIResponseException when the API is not returning an OK response

```php
try {
    $response = $client->get('/endpoint', $session);
} catch (\Classy\Exceptions\APIResponseException $e) {
    // Get the HTTP response code
    $code = $e->getCode();
    // Get the response content
    $content = $e->getResponseData();
    // Get the response headers
    $headers = $e->getResponseHeaders();
}
```
