<?php
declare(strict_types=1);

namespace App;

final class Config
{
    private static ?array $data = null;

    private static function load(): array
    {
        if (self::$data === null) {
            self::$data = require __DIR__ . '/../config.php';
        }
        return self::$data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::load()[$key] ?? $default;
    }
}
