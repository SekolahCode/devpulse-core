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

        // After validateConfig(), dsn is guaranteed to be a valid URL string.
        // timeout and async are expected to be int/bool — assert narrows types for PHPStan.
        $dsn     = $this->config['dsn'];
        $timeout = $this->config['timeout'];
        $async   = $this->config['async'];

        assert(is_string($dsn));
        assert(is_int($timeout));
        assert(is_bool($async));

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
    public function captureException(\Throwable $e, array $extra = []): void
    {
        if (!$this->config['enabled']) return;

        // Merge config keys into $extra before passing — Payload::fromThrowable merges
        // $extra first, so library-controlled keys (environment, release) always win.
        $payload = Payload::fromThrowable($e, array_merge($extra, [
            'environment' => $this->config['environment'],
            'release'     => $this->config['release'],
        ]));

        $this->transport->send($payload);
    }

    /** @param array<string, mixed> $extra */
    public function captureMessage(string $message, string $level = 'info', array $extra = []): void
    {
        if (!$this->config['enabled']) return;

        $payload = Payload::fromMessage($message, $level, array_merge($extra, [
            'environment' => $this->config['environment'],
            'release'     => $this->config['release'],
        ]));

        $this->transport->send($payload);
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
        if (!in_array($error['type'], $fatals)) return;

        $this->captureException(
            new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
        );
    }
}
