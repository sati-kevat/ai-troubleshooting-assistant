<?php

declare(strict_types=1);

/**
 * Writes server-side diagnostics to backend/storage/logs/app.log.
 * Log files are git-ignored and blocked from web access.
 */
final class Logger
{
    private static string $file = '';

    public static function init(string $file): void
    {
        self::$file = $file;
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "[%s] %s %s%s\n",
            date('c'),
            $level,
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );
        if (self::$file === '' || @file_put_contents(self::$file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(trim($line));
        }
    }
}
