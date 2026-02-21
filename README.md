# devpulse/core

PHP core SDK for DevPulse — low-level error capture and HTTP transport.

This package is the foundation used by the [Laravel](../laravel) integration and can be used standalone in any PHP project.

## Requirements

- PHP 8.1+
- A running DevPulse server

## Installation

```bash
composer require devpulse/core
```

## Usage

### Basic Setup

```php
use DevPulse\Client;

$client = new Client([
    'dsn'         => 'http://localhost:8000/api/ingest/YOUR_API_KEY',
    'environment' => 'production',
    'release'     => '1.0.0',   // optional
    'enabled'     => true,
    'async'       => true,      // fire-and-forget HTTP (non-blocking)
    'timeout'     => 2,         // seconds
]);

// Register global error/exception handlers
$client->register();
```

After calling `register()`, all unhandled exceptions, PHP errors, and fatal shutdown errors are captured automatically.

### Manual Capture

```php
try {
    riskyOperation();
} catch (\Throwable $e) {
    $client->captureException($e);
}

// Capture a plain message
$client->captureMessage('Something noteworthy happened', 'warning');
```

### Static Facade

```php
use DevPulse\DevPulse;

DevPulse::init([
    'dsn' => 'http://localhost:8000/api/ingest/YOUR_API_KEY',
]);

DevPulse::captureException($e);
DevPulse::captureMessage('hello');
```

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

## License

MIT — see [LICENSE](../../LICENSE)
