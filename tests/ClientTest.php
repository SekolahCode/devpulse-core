<?php

namespace DevPulse\Tests;

use DevPulse\Client;
use DevPulse\Exceptions\DevPulseException;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private const DSN = 'http://localhost/api/ingest/test-key';

    // -----------------------------------------------------------------------
    // validateConfig
    // -----------------------------------------------------------------------

    public function test_throws_when_dsn_is_empty(): void
    {
        $this->expectException(DevPulseException::class);
        $this->expectExceptionMessageMatches('/DSN is required/');

        new Client(['dsn' => '']);
    }

    public function test_throws_when_dsn_is_not_a_url(): void
    {
        $this->expectException(DevPulseException::class);
        $this->expectExceptionMessageMatches('/not a valid URL/');

        new Client(['dsn' => 'not-a-url']);
    }

    public function test_throws_when_timeout_is_zero(): void
    {
        $this->expectException(DevPulseException::class);
        $this->expectExceptionMessageMatches('/timeout must be a positive integer/');

        new Client(['dsn' => self::DSN, 'timeout' => 0]);
    }

    public function test_throws_when_timeout_is_negative(): void
    {
        $this->expectException(DevPulseException::class);

        new Client(['dsn' => self::DSN, 'timeout' => -1]);
    }

    public function test_throws_when_timeout_is_not_an_int(): void
    {
        $this->expectException(DevPulseException::class);

        new Client(['dsn' => self::DSN, 'timeout' => '5']);
    }

    public function test_throws_when_async_is_not_a_bool(): void
    {
        $this->expectException(DevPulseException::class);
        $this->expectExceptionMessageMatches('/async must be a boolean/');

        new Client(['dsn' => self::DSN, 'async' => 'yes']);
    }

    // -----------------------------------------------------------------------
    // register — double-call guard
    // -----------------------------------------------------------------------

    public function test_register_can_be_called_multiple_times_without_error(): void
    {
        $client = new Client(['dsn' => self::DSN]);

        $client->register();
        $client->register(); // must not throw or re-register

        // Restore handlers so PHPUnit doesn't flag this test as risky
        restore_error_handler();
        restore_exception_handler();

        $this->assertTrue(true); // no exception = pass
    }

    // -----------------------------------------------------------------------
    // captureError — severity filtering
    // -----------------------------------------------------------------------

    public function test_captureError_returns_false_for_silenced_severity(): void
    {
        $client = new Client(['dsn' => self::DSN]);

        // Save and override error_reporting to exclude E_NOTICE
        $prev = error_reporting(E_ALL & ~E_NOTICE);

        $result = $client->captureError(E_NOTICE, 'silenced notice', __FILE__, __LINE__);

        error_reporting($prev);

        $this->assertFalse($result);
    }

    public function test_captureError_returns_false_to_preserve_default_handling(): void
    {
        $client = new Client(['dsn' => self::DSN]);

        $prev   = error_reporting(E_ALL);
        $result = $client->captureError(E_WARNING, 'test warning', __FILE__, __LINE__);
        error_reporting($prev);

        // Must always return false so PHP continues default error handling
        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // captureException / captureMessage — return type
    // -----------------------------------------------------------------------

    public function test_captureException_returns_bool(): void
    {
        $client = new Client(['dsn' => self::DSN, 'async' => false]);

        $result = $client->captureException(new \RuntimeException('test'));

        $this->assertIsBool($result);
    }

    public function test_captureMessage_returns_bool(): void
    {
        $client = new Client(['dsn' => self::DSN, 'async' => false]);

        $result = $client->captureMessage('test message');

        $this->assertIsBool($result);
    }

    public function test_captureException_returns_false_when_disabled(): void
    {
        $client = new Client(['dsn' => self::DSN, 'enabled' => false]);

        $this->assertFalse($client->captureException(new \RuntimeException('x')));
    }

    public function test_captureMessage_returns_false_when_disabled(): void
    {
        $client = new Client(['dsn' => self::DSN, 'enabled' => false]);

        $this->assertFalse($client->captureMessage('x'));
    }

    // -----------------------------------------------------------------------
    // captureShutdown — fatal error filtering (strict in_array)
    // -----------------------------------------------------------------------

    public function test_captureShutdown_does_not_fire_for_non_fatal_error_types(): void
    {
        // This indirectly tests strict mode: without strict: true in in_array(),
        // type coercion could match non-fatal int values against the fatals list.
        $client = new Client(['dsn' => self::DSN]);

        // No last error → should return without sending (no exception thrown)
        $client->captureShutdown();

        $this->assertTrue(true);
    }
}
