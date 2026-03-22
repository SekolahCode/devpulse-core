<?php

namespace DevPulse;

class Client
{
    private Transport $transport;

    /** @var array<string, mixed> */
    private array $config;

    private bool $registered = false;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->config = array_merge([
            'dsn'         => '',
            'environment' => 'production',
            'release'     => null,
            'timeout'     => 2,
            'async'       => true,
            'enabled'     => true,
        ], $config);

        $this->validateConfig();

        // validateConfig() guarantees these types — no assertions needed.
        /** @var string $dsn */
        $dsn = $this->config['dsn'];
        /** @var int $timeout */
        $timeout = $this->config['timeout'];
        /** @var bool $async */
        $async = $this->config['async'];

        $this->transport = new Transport($dsn, $timeout, $async);
    }

    private function validateConfig(): void
    {
        $dsn = $this->config['dsn'];

        if (!is_string($dsn) || $dsn === '') {
            throw new Exceptions\DevPulseException('DevPulse DSN is required and must be a string.');
        }

        if (!filter_var($dsn, FILTER_VALIDATE_URL)) {
            throw new Exceptions\DevPulseException("DevPulse DSN is not a valid URL: \"{$dsn}\"");
        }

        $timeout = $this->config['timeout'];
        if (!is_int($timeout) || $timeout < 1) {
            throw new Exceptions\DevPulseException('timeout must be a positive integer.');
        }

        if (!is_bool($this->config['async'])) {
            throw new Exceptions\DevPulseException('async must be a boolean.');
        }
    }

    public function register(): void
    {
        if ($this->registered || !$this->config['enabled']) return;

        set_exception_handler([$this, 'captureException']);
        set_error_handler([$this, 'captureError']);
        register_shutdown_function([$this, 'captureShutdown']);

        $this->registered = true;
    }

    /** @param array<string, mixed> $extra */
    public function captureException(\Throwable $e, array $extra = []): bool
    {
        if (!$this->config['enabled']) return false;

        // Merge config keys into $extra before passing — Payload::fromThrowable merges
        // $extra first, so library-controlled keys (environment, release) always win.
        $payload = Payload::fromThrowable($e, array_merge($extra, [
            'environment' => $this->config['environment'],
            'release'     => $this->config['release'],
        ]));

        return $this->transport->send($payload);
    }

    /** @param array<string, mixed> $extra */
    public function captureMessage(string $message, string $level = 'info', array $extra = []): bool
    {
        if (!$this->config['enabled']) return false;

        $payload = Payload::fromMessage($message, $level, array_merge($extra, [
            'environment' => $this->config['environment'],
            'release'     => $this->config['release'],
        ]));

        return $this->transport->send($payload);
    }

    // Called by set_error_handler
    public function captureError(int $severity, string $message, string $file, int $line): bool
    {
        // Respect error_reporting settings
        if (!(error_reporting() & $severity)) return false;

        $this->captureException(new \ErrorException($message, 0, $severity, $file, $line));
        return false; // Let PHP continue default error handling
    }

    // Called by register_shutdown_function — catches fatal errors
    public function captureShutdown(): void
    {
        $error = error_get_last();
        if (!$error) return;

        $fatals = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatals, true)) return;

        $this->captureException(
            new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
        );
    }
}
