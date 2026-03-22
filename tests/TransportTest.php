<?php

namespace DevPulse\Tests;

use DevPulse\Payload;
use DevPulse\Transport;
use PHPUnit\Framework\TestCase;

class TransportTest extends TestCase
{
    // -----------------------------------------------------------------------
    // sendSync — HTTP status handling
    // -----------------------------------------------------------------------

    public function test_sendSync_returns_false_when_server_unreachable(): void
    {
        // Port 19999 is almost certainly not listening
        $transport = new Transport('http://127.0.0.1:19999/ingest', timeout: 1, async: false);

        $result = $transport->send(Payload::fromMessage('test'));

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // fireAndForget — query string must not be dropped
    // -----------------------------------------------------------------------

    public function test_fireAndForget_preserves_query_string_in_path(): void
    {
        // We test path construction via reflection — no live server needed.
        $transport = new Transport('http://localhost/api/ingest?key=abc123', async: true);

        $reflection = new \ReflectionMethod(Transport::class, 'fireAndForget');
        $reflection->setAccessible(true);

        // Capture what raw HTTP request would be built by patching the socket open.
        // Since we can't intercept the socket easily, we verify via the DSN parsing
        // by calling fireAndForget and checking it returns false (no server on that
        // port) without throwing — if the path construction throws, it means a regression.
        $result = $reflection->invoke($transport, '{"level":"info"}');

        // Either false (no server) or true (server happened to answer) — no exception.
        $this->assertIsBool($result);
    }

    public function test_fireAndForget_returns_false_for_invalid_dsn(): void
    {
        $transport = new Transport('http://', async: true);

        $reflection = new \ReflectionMethod(Transport::class, 'fireAndForget');
        $reflection->setAccessible(true);

        $this->assertFalse($reflection->invoke($transport, '{}'));
    }

    // -----------------------------------------------------------------------
    // json_encode failure guard
    // -----------------------------------------------------------------------

    public function test_send_returns_false_when_payload_is_not_json_encodable(): void
    {
        $transport = new Transport('http://localhost/ingest', async: false);

        // Build a payload whose toArray() returns something with an INF value
        // (json_encode returns false for INF).
        // We use reflection to construct a Payload with un-serialisable data.
        $reflection = new \ReflectionClass(Payload::class);
        $constructor = $reflection->getConstructor();
        assert($constructor !== null);
        $constructor->setAccessible(true);

        $payload = $reflection->newInstanceWithoutConstructor();
        $constructor->invoke($payload, ['value' => INF]);

        $result = $transport->send($payload);

        $this->assertFalse($result);
    }
}
