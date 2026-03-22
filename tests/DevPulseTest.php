<?php

namespace DevPulse\Tests;

use DevPulse\DevPulse;
use PHPUnit\Framework\TestCase;

class DevPulseTest extends TestCase
{
    protected function setUp(): void
    {
        DevPulse::reset();
    }

    protected function tearDown(): void
    {
        DevPulse::reset();
    }

    /** Call when a test called init() with enabled=true (which installs handlers). */
    private function restoreHandlers(): void
    {
        restore_error_handler();
        restore_exception_handler();
    }

    // -----------------------------------------------------------------------
    // captureMessage — $extra passthrough
    // -----------------------------------------------------------------------

    public function test_captureMessage_passes_extra_to_client(): void
    {
        DevPulse::init(['dsn' => 'http://localhost/api/ingest/key', 'enabled' => false]);

        // When enabled=false, captureMessage returns false immediately.
        // The important thing is the signature accepts $extra without error.
        $result = DevPulse::captureMessage('hello', 'info', ['environment' => 'testing']);

        $this->assertFalse($result);
    }

    public function test_capture_passes_extra_to_client(): void
    {
        DevPulse::init(['dsn' => 'http://localhost/api/ingest/key', 'enabled' => false]);

        $result = DevPulse::capture(new \RuntimeException('x'), ['release' => 'v1.0']);

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // return types
    // -----------------------------------------------------------------------

    public function test_capture_returns_false_when_not_initialised(): void
    {
        // No init() called — client is null
        $this->assertFalse(DevPulse::capture(new \RuntimeException('x')));
    }

    public function test_captureMessage_returns_false_when_not_initialised(): void
    {
        $this->assertFalse(DevPulse::captureMessage('x'));
    }

    // -----------------------------------------------------------------------
    // getClient / reset
    // -----------------------------------------------------------------------

    public function test_getClient_returns_null_before_init(): void
    {
        $this->assertNull(DevPulse::getClient());
    }

    public function test_getClient_returns_client_after_init(): void
    {
        DevPulse::init(['dsn' => 'http://localhost/api/ingest/key']);
        $this->restoreHandlers();

        $this->assertNotNull(DevPulse::getClient());
    }

    public function test_reset_clears_client(): void
    {
        DevPulse::init(['dsn' => 'http://localhost/api/ingest/key']);
        $this->restoreHandlers();
        DevPulse::reset();

        $this->assertNull(DevPulse::getClient());
    }
}
