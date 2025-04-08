# LrucHTTP
### - Lightweight resource-utilizing client for HTTP.
A simple, fluent PHP HTTP client library for making API requests with minimal configuration.

## Features

- Fluent, chainable API
- Support for all standard HTTP methods
- JSON and form data handling
- Cookie management
- Proxy support
- Automatic retry functionality
- Automatic response parsing (JSON, XML, Text)
- SSL verification options
- Redirect handling
- Timeout configuration
- Comprehensive error handling
- Download functionality
- Logging support

## Installation

```bash
composer require xenoxyt/lruchttp
```

## Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

$client = new LrucHTTPClient();

// Simple GET request
$response = $client->get('https://api.example.com/data');
echo $response['parsed']; // Automatically parsed based on Content-Type

// POST JSON data
$response = $client->postJson('https://api.example.com/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Download a file
$client->download('https://example.com/large-file.zip', '/path/to/save/file.zip');
```

## Advanced Usage

### Chaining Methods

```php
$response = $client->prepareReq('POST', 'https://api.example.com/data')
    ->setHeader([
        'X-API-Key' => 'your-api-key',
        'Accept' => 'application/json'
    ])
    ->setJsonBody(['key' => 'value'])
    ->timeout(5000) // 5 seconds
    ->retry(3, 1000) // retry 3 times with 1 second between attempts
    ->sendReq();
```

### Using Configuration Array

```php
$response = $client->req([
    'Method' => 'POST',
    'URL' => 'https://api.example.com/data',
    'Header' => [
        'X-API-Key' => 'your-api-key'
    ],
    'JsonBody' => [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ],
    'Timeout' => 5000,
    'Retry' => 3
]);
```

### Authentication

```php
// Basic Auth
$response = $client->prepareReq('GET', 'https://api.example.com/protected')
    ->setBasicAuth('username', 'password')
    ->sendReq();

// Bearer Token
$response = $client->prepareReq('GET', 'https://api.example.com/protected')
    ->setAuthorization('Bearer', 'your-token-here')
    ->sendReq();
```

### Working with Cookies

```php
$client->prepareReq('GET', 'https://api.example.com')
    ->addCookie('session_id', 'abc123')
    ->sendReq();

// After response, get cookies that were set
$nextRequest = $client->prepareReq('POST', 'https://api.example.com/next')
    ->setCookie($response['headers']['cookies'])
    ->sendReq();
```

### Custom Logging

```php
$client->setLogger(function($message, $level) {
    echo "[$level] $message\n";
});
```

### Handling Different Response Formats

```php
// Force JSON parsing regardless of Content-Type
$response = $client->prepareReq('GET', 'https://api.example.com/data')
    ->expectFormat('json')
    ->sendReq();
```

## Response Structure

The response from any request is an associative array with the following keys:

- `success`: Boolean indicating if the request completed without errors
- `status_code`: HTTP status code (e.g., 200, 404, 500)
- `body`: Raw response body
- `parsed`: Response body parsed according to content type
- `headers`: Associative array of response headers
- `error`: Error information (if request failed)
- `attempts`: Number of attempts made

## Error Handling

```php
$response = $client->get('https://api.example.com/data');

if (!$response['success']) {
    echo "Request failed: " . $response['error']['message'];
} else if ($response['status_code'] >= 400) {
    echo "Server error: " . $response['status_code'];
} else {
    // Process successful response
    $data = $response['parsed'];
}
```

## License

MIT License - Copyright (c) 2025 XenoXYT
