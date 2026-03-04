<?php

namespace ProAI\DataIntegrity;

class AuditManager
{
    /**
     * The default chunk size for audits.
     *
     * @var int
     */
    protected static int $defaultChunkSize = 1000;

    /**
     * The base path where audits are discovered.
     *
     * @var string
     */
    protected static string $auditsPath = 'database/audits';

    /**
     * The registry of named check aliases.
     *
     * @var array<string, class-string<IntegrityCheck>>
     */
    private static array $registry = [];

    /**
     * Set the default chunk size for audits.
     *
     * @return void
     */
    public static function defaultChunkSize(int $chunkSize): void
    {
        static::$defaultChunkSize = $chunkSize;
    }

    /**
     * Override the default audit discovery path.
     *
     * @return void
     */
    public static function discoverIn(string $path): void
    {
        static::$auditsPath = $path;
    }

    /**
     * Register a named check alias.
     *
     * @param  class-string<IntegrityCheck>  $checkClass
     * @return void
     */
    public static function register(string $name, string $checkClass): void
    {
        static::$registry[$name] = $checkClass;
    }

    /**
     * Reset all settings to their defaults.
     *
     * @return void
     */
    public static function flush(): void
    {
        static::$defaultChunkSize = 1000;
        static::$auditsPath = 'database/audits';
        static::$registry = [];
    }

    /**
     * Resolve a check alias to its class, or return the class as-is.
     *
     * @return string
     */
    public static function resolveCheck(string $nameOrClass): string
    {
        return static::$registry[$nameOrClass] ?? $nameOrClass;
    }

    /**
     * Get the default chunk size.
     *
     * @return int
     */
    public static function getDefaultChunkSize(): int
    {
        return static::$defaultChunkSize;
    }

    /**
     * Get the audits discovery path.
     *
     * @return string
     */
    public static function getAuditsPath(): string
    {
        return static::$auditsPath;
    }
}
