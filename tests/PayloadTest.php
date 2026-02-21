<?php

namespace DevPulse\Tests;

use DevPulse\Payload;
use PHPUnit\Framework\TestCase;

class PayloadTest extends TestCase
{
    // -----------------------------------------------------------------------
    // fromThrowable
    // -----------------------------------------------------------------------

    public function test_fromThrowable_returns_payload_instance(): void
    {
        $payload = Payload::fromThrowable(new \RuntimeException('boom'));

        $this->assertInstanceOf(Payload::class, $payload);
    }

    public function test_fromThrowable_contains_required_keys(): void
    {
        $data = Payload::fromThrowable(new \RuntimeException('boom'))->toArray();

        $this->assertSame('error', $data['level']);
        $this->assertArrayHasKey('exception', $data);
        $this->assertArrayHasKey('context', $data);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function test_fromThrowable_captures_exception_details(): void
    {
        $e    = new \InvalidArgumentException('bad input', 42);
        $data = Payload::fromThrowable($e)->toArray();

        $exception = $data['exception'];
        assert(is_array($exception));

        $this->assertSame(\InvalidArgumentException::class, $exception['type']);
        $this->assertSame('bad input', $exception['message']);
        $this->assertSame(42, $exception['code']);
        $this->assertIsArray($exception['stacktrace']);
        $this->assertNotEmpty($exception['stacktrace']);
    }

    public function test_fromThrowable_stacktrace_args_are_empty(): void
    {
        $data = Payload::fromThrowable(new \RuntimeException('x'))->toArray();

        $exception = $data['exception'];
        assert(is_array($exception));

        $frames = $exception['stacktrace'];
        assert(is_array($frames));

        // Skip the first frame (throw site — has no args key)
        foreach (array_slice($frames, 1) as $frame) {
            assert(is_array($frame));
            $this->assertSame([], $frame['args'], 'Args must be empty to avoid leaking sensitive data');
        }
    }

    public function test_fromThrowable_library_keys_win_over_extra(): void
    {
        // Callers must never be able to overwrite 'level' or 'timestamp' via $extra
        $data = Payload::fromThrowable(
            new \RuntimeException('x'),
            ['level' => 'debug', 'timestamp' => 'fake']
        )->toArray();

        $this->assertSame('error', $data['level']);
        $this->assertNotSame('fake', $data['timestamp']);
    }

    public function test_fromThrowable_extra_keys_are_included(): void
    {
        $data = Payload::fromThrowable(
            new \RuntimeException('x'),
            ['environment' => 'staging', 'release' => 'v2.0']
        )->toArray();

        $this->assertSame('staging', $data['environment']);
        $this->assertSame('v2.0', $data['release']);
    }

    // -----------------------------------------------------------------------
    // fromMessage
    // -----------------------------------------------------------------------

    public function test_fromMessage_returns_payload_instance(): void
    {
        $this->assertInstanceOf(Payload::class, Payload::fromMessage('hello'));
    }

    public function test_fromMessage_contains_required_keys(): void
    {
        $data = Payload::fromMessage('hello', 'warning')->toArray();

        $this->assertSame('warning', $data['level']);
        $this->assertSame('hello', $data['message']);
        $this->assertArrayHasKey('context', $data);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function test_fromMessage_defaults_to_info_level(): void
    {
        $data = Payload::fromMessage('test')->toArray();

        $this->assertSame('info', $data['level']);
    }

    public function test_fromMessage_library_keys_win_over_extra(): void
    {
        $data = Payload::fromMessage('test', 'info', ['level' => 'debug'])->toArray();

        $this->assertSame('info', $data['level']);
    }

    // -----------------------------------------------------------------------
    // buildContext
    // -----------------------------------------------------------------------

    public function test_context_contains_runtime_info(): void
    {
        $context = Payload::fromMessage('test')->toArray()['context'];
        assert(is_array($context));

        $this->assertSame(PHP_VERSION, $context['php']);
        $this->assertSame(PHP_OS, $context['os']);
        $this->assertSame(PHP_SAPI, $context['sapi']);
        $this->assertIsInt($context['memory']);
    }

    // -----------------------------------------------------------------------
    // buildRequest — CLI context
    // -----------------------------------------------------------------------

    public function test_request_is_null_in_cli(): void
    {
        // Tests always run via CLI SAPI, so request must be null
        $data = Payload::fromMessage('test')->toArray();

        $this->assertNull($data['request']);
    }

    // -----------------------------------------------------------------------
    // timestamp
    // -----------------------------------------------------------------------

    public function test_timestamp_is_valid_iso8601(): void
    {
        $timestamp = Payload::fromMessage('test')->toArray()['timestamp'];
        assert(is_string($timestamp));

        $this->assertNotFalse(
            \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $timestamp),
            "timestamp must be a valid ISO 8601 string, got: {$timestamp}"
        );
    }
}
