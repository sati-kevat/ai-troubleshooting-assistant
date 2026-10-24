<?php

declare(strict_types=1);

/**
 * Loads configuration from backend/.env (if present) and real environment
 * variables. Real environment variables take precedence over the file.
 */
final class Config
{
    private static array $values = [];

    public static function load(string $envFile): void
    {
        if (is_readable($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
                    $value = $m[2];
                }
                self::$values[$key] = $value;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        $value = self::$values[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);
        return ($value !== null && is_numeric($value)) ? (int) $value : $default;
    }

    public static function list(string $key): array
    {
        $value = self::get($key, '');
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
