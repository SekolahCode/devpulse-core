<?php

namespace DevPulse;

class Payload
{
    /** @param array<string, mixed> $data */
    private function __construct(private readonly array $data) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    /** @param array<string, mixed> $extra */
    public static function fromThrowable(\Throwable $e, array $extra = []): self
    {
        // $extra is merged first so library-controlled keys (level, timestamp, etc.) always win
        return new self(array_merge($extra, [
            'level'     => 'error',
            'exception' => [
                'type'       => get_class($e),
                'message'    => $e->getMessage(),
                'code'       => $e->getCode(),
                'stacktrace' => self::buildStacktrace($e),
            ],
            'context'   => self::buildContext(),
            'request'   => self::buildRequest(),
            'timestamp' => gmdate('c'),
        ]));
    }

    /** @param array<string, mixed> $extra */
    public static function fromMessage(string $message, string $level = 'info', array $extra = []): self
    {
        // $extra is merged first so library-controlled keys always win
        return new self(array_merge($extra, [
            'level'     => $level,
            'message'   => $message,
            'context'   => self::buildContext(),
            'request'   => self::buildRequest(),
            'timestamp' => gmdate('c'),
        ]));
    }

    /** @return list<array<string, mixed>> */
    private static function buildStacktrace(\Throwable $e): array
    {
        $frames = [];

        // First frame — where exception was thrown
        $frames[] = [
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
            'function' => null,
            'context'  => self::readSourceContext($e->getFile(), $e->getLine()),
        ];

        foreach ($e->getTrace() as $frame) {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;
            $frames[] = [
                'file'     => $file,
                'line'     => $line,
                // 'type' (?? '') handles frames without a class separator (plain functions)
                'function' => isset($frame['class'])
                    ? $frame['class'] . ($frame['type'] ?? '') . $frame['function']
                    : $frame['function'],
                'args'     => [],   // intentionally empty — avoid leaking sensitive data
                'context'  => self::readSourceContext($file, $line),
            ];
        }

        return $frames;
    }

    /**
     * @return array{start: int, lines: array<int, string>}|null
     */
    private static function readSourceContext(?string $file, ?int $line, int $lines = 5): ?array
    {
        if (!$file || !$line || !file_exists($file)) return null;

        try {
            $spl   = new \SplFileObject($file);
            $start = max(0, $line - $lines - 1);
            $spl->seek($start);

            $slice = [];
            for ($i = $start; $i < $line + $lines && !$spl->eof(); $i++) {
                $raw = $spl->current();
                $slice[$i + 1] = is_string($raw) ? rtrim($raw) : '';
                $spl->next();
            }
        } catch (\RuntimeException $e) {
            return null;
        }

        return $slice !== [] ? ['start' => $start + 1, 'lines' => $slice] : null;
    }

    /** @return array{php: string, os: string, sapi: string, memory: int} */
    private static function buildContext(): array
    {
        return [
            'php'    => PHP_VERSION,
            'os'     => PHP_OS,
            'sapi'   => PHP_SAPI,
            'memory' => memory_get_peak_usage(true),
        ];
    }

    /**
     * @return array{url: string, method: string, ip: string|null, headers: array<string, string>}|null
     */
    private static function buildRequest(): ?array
    {
        // Skip in CLI context (queue workers, artisan commands, etc.)
        if (PHP_SAPI === 'cli') return null;

        return [
            'url'     => (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                . '://' . self::serverString('HTTP_HOST', 'localhost')
                . self::serverString('REQUEST_URI', '/'),
            'method'  => self::serverString('REQUEST_METHOD', 'GET'),
            'ip'      => self::resolveClientIp(),
            'headers' => self::safeHeaders(),
        ];
    }

    /**
     * Resolve the real client IP, accounting for reverse proxies and CDNs.
     *
     * Checks X-Forwarded-For → X-Real-IP → REMOTE_ADDR in order.
     * The first valid IP in X-Forwarded-For is the original client.
     */
    private static function resolveClientIp(): ?string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (empty($_SERVER[$key])) continue;

            $ip = trim(explode(',', self::serverString($key))[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return null;
    }

    /** Safely reads a string value from $_SERVER with a fallback default. */
    private static function serverString(string $key, string $default = ''): string
    {
        $value = $_SERVER[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }

    /** @return array<string, string> */
    private static function safeHeaders(): array
    {
        if (!function_exists('getallheaders')) return [];

        // Strip headers that may carry credentials or session tokens
        $blocked = ['authorization', 'cookie', 'x-api-key'];

        // PHPStan's stub types getallheaders() as array without value types;
        // the @var annotation tells it the actual shape so array_filter can propagate it.
        /** @var array<string, string> $all */
        $all = getallheaders();

        return array_filter(
            $all,
            static fn(string $key): bool => !in_array(strtolower($key), $blocked, strict: true),
            ARRAY_FILTER_USE_KEY
        );
    }
}
