<?php

namespace DevPulse;

class Transport
{
    private string $endpoint;
    private string $apiKey;
    private int    $timeout;
    private bool   $async;

    public function __construct(string $dsn, int $timeout = 2, bool $async = true)
    {
        // DSN format: https://<host>/api/ingest/<api_key>
        // Extract the key so it is sent as X-API-Key header rather than in the URL,
        // preventing leakage in server access logs and CDN logs.
        [$this->endpoint, $this->apiKey] = self::parseDsn($dsn);
        $this->timeout = $timeout;
        $this->async   = $async;
    }

    /**
     * @return array{string, string} [endpoint, apiKey]
     */
    private static function parseDsn(string $dsn): array
    {
        $parts  = explode('/', rtrim($dsn, '/'));
        $apiKey = array_pop($parts);
        return [implode('/', $parts), $apiKey];
    }

    public function send(Payload $payload): bool
    {
        $json = json_encode($payload->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return false;

        // Async — send after the response is flushed to the client so the
        // event is never lost due to early process termination.
        // fastcgi_finish_request() (PHP-FPM) flushes the response buffer first,
        // then we do a normal sync send. The user sees no added latency.
        if ($this->async && PHP_SAPI !== 'cli') {
            $endpoint = $this->endpoint;
            $apiKey   = $this->apiKey;
            $timeout  = $this->timeout;
            register_shutdown_function(static function () use ($json, $endpoint, $apiKey, $timeout): void {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                (new self("{$endpoint}/{$apiKey}", $timeout, false))->sendSync($json);
            });
            return true;
        }

        return $this->sendSync($json);
    }

    private function sendSync(string $json): bool
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nX-API-Key: {$this->apiKey}\r\n",
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

        $result = file_get_contents($this->endpoint, false, $context);

        restore_error_handler();

        if ($result === false) return false;

        // Check HTTP status — ignore_errors keeps the body but we still want to
        // surface 4xx/5xx as failures so callers know the event wasn't accepted.
        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/HTTP\/\S+\s+(\d+)/', $statusLine, $m)) {
            $status = (int) $m[1];
            if ($status < 200 || $status >= 300) return false;
        }

        return true;
    }


}
