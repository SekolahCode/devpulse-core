<?php

namespace DevPulse;

class DevPulse
{
    private static ?Client $client = null;

    /** @param array<string, mixed> $config */
    public static function init(array $config): void
    {
        self::$client = new Client($config);
        self::$client->register();
    }

    /** @param array<string, mixed> $extra */
    public static function capture(\Throwable $e, array $extra = []): void
    {
        self::$client?->captureException($e, $extra);
    }

    public static function captureMessage(string $message, string $level = 'info'): void
    {
        self::$client?->captureMessage($message, $level);
    }

    public static function getClient(): ?Client
    {
        return self::$client;
    }

    public static function reset(): void
    {
        self::$client = null;
    }
}
