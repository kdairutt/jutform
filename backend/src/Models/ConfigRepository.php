<?php

namespace JutForm\Models;

use JutForm\Core\Database;
use PDO;

class ConfigRepository
{
    public static function get(string $key): ?string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT value FROM app_config WHERE config_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string) $row['value'] : null;
    }

    public static function set(string $key, string $value): void
    {
        $pdo = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO app_config (config_key, value, created_at, updated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([$key, $value, $now, $now]);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v !== null ? (int) $v : $default;
    }
}
