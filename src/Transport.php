<?php

namespace DevPulse;

class Transport
{
    private string $dsn;
    private int    $timeout;
    private bool   $async;

    public function __construct(string $dsn, int $timeout = 2, bool $async = true)
    {
        $this->dsn     = $dsn;
        $this->timeout = $timeout;
        $this->async   = $async;
    }

    public function send(Payload $payload): bool
    {
        $json = json_encode($payload->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$json) return false;

        // Async — fire and forget, don't block the request
        if ($this->async && PHP_SAPI !== 'cli') {
            return $this->fireAndForget($json);
        }

        return $this->sendSync($json);
    }

    private function sendSync(string $json): bool
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\n",
                'content'       => $json,
                'timeout'       => $this->timeout,
                'ignore_errors' => true,
            ]
        ]);

        // Use set_error_handler to capture stream errors without @ suppression
        $streamError = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$streamError): bool {
            $streamError = $errstr;
            return true;
        });

        $result = file_get_contents($this->dsn, false, $context);

        restore_error_handler();

        return $result !== false;
    }

    private function fireAndForget(string $json): bool
    {
        // Parse DSN URL
        $parts = parse_url($this->dsn);
        if (!$parts || empty($parts['host'])) return false;

        $scheme = $parts['scheme'] ?? 'http';
        $isHttps = $scheme === 'https';
        $port   = $parts['port'] ?? ($isHttps ? 443 : 80);
        $path   = $parts['path'] ?? '/';
        $length = strlen($json);

        // Prefix host with ssl:// for HTTPS connections
        $socketHost = $isHttps ? "ssl://{$parts['host']}" : $parts['host'];

        $request = "POST {$path} HTTP/1.1\r\n"
            . "Host: {$parts['host']}\r\n"
            . "Content-Type: application/json\r\n"
            . "Content-Length: {$length}\r\n"
            . "Connection: close\r\n\r\n"
            . $json;

        // Open socket but don't wait for response
        $fp = fsockopen($socketHost, $port, $errno, $errstr, 1);
        if (!$fp) return false;

        stream_set_timeout($fp, 1);
        fwrite($fp, $request);
        fclose($fp);

        return true;
    }
}
