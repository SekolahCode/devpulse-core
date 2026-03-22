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
    public static function capture(\Throwable $e, array $extra = []): bool
    {
        return self::$client?->captureException($e, $extra) ?? false;
    }

    /** @param array<string, mixed> $extra */
    public static function captureMessage(string $message, string $level = 'info', array $extra = []): bool
    {
        return self::$client?->captureMessage($message, $level, $extra) ?? false;
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
