# devpulse/core

PHP core SDK for DevPulse — low-level error capture and HTTP transport.

This package is the foundation used by the [Laravel](../laravel) integration and can be used standalone in any PHP 8.1+ project.

## Requirements

- PHP 8.1+
- A running DevPulse server (v1.0+)

## Installation

```bash
composer require devpulse/core
```

## Quick Start

```php
use DevPulse\Client;

$client = new Client([
    'dsn'         => 'https://your-devpulse-host/api/ingest/YOUR_API_KEY',
    'environment' => 'production',
    'release'     => '1.0.0',   // optional
    'async'       => true,      // fire-and-forget via register_shutdown_function (default)
    'timeout'     => 2,         // HTTP timeout in seconds
]);

// Register global error/exception/shutdown handlers
$client->register();
```

After `register()`, all unhandled exceptions, PHP errors, and fatal shutdown errors are captured automatically.

## Manual Capture

```php
try {
    riskyOperation();
} catch (\Throwable $e) {
    $client->captureException($e, ['order_id' => $orderId]);
}

$client->captureMessage('Payment gateway timeout', 'warning', ['gateway' => 'stripe']);
```

## Static Facade

```php
use DevPulse\DevPulse;

DevPulse::init([
    'dsn'         => 'https://your-devpulse-host/api/ingest/YOUR_API_KEY',
    'environment' => 'production',
]);

DevPulse::captureException($e);
DevPulse::captureMessage('Something happened', 'info');
```

## What's Captured

Each event automatically includes:

- **Exception type, message, and stack trace** with source context (±5 lines)
- **PHP version, OS, SAPI, and peak memory**
- **Request context** — URL, method, client IP, sanitised headers (no auth/cookie/x-api-key)
- **SDK version** — `devpulse-php/2.0.0` for SDK version tracking

### IP resolution

The SDK reads `X-Forwarded-For` only when `REMOTE_ADDR` is a known trusted proxy, preventing IP spoofing. The default trusted ranges are RFC-1918 private networks.

## Development

```bash
composer install
vendor/bin/phpunit          # run tests
vendor/bin/phpstan analyse  # static analysis
```

## License

MIT — see [LICENSE](../../LICENSE)
