<?php

namespace JutForm\Models;

use JutForm\Core\Database;
use PDO;

class KeyValueStore
{
    public static function get(int $formId, string $key): ?string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT value FROM form_settings WHERE form_id = ? AND setting_key = ? LIMIT 1'
        );
        $stmt->execute([$formId, $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (string) $row['value'] : null;
    }

    public static function getBool(int $formId, string $key): bool
    {
        $v = self::get($formId, $key);
        return $v === 'true';
    }

    public static function set(int $formId, string $key, string $value): void
    {
        $pdo = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO form_settings (form_id, setting_key, value, updated_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([$formId, $key, $value, $now]);
    }

    public static function allForForm(int $formId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT setting_key, value FROM form_settings WHERE form_id = ?'
        );
        $stmt->execute([$formId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $out[$row['setting_key']] = $row['value'];
        }
        return $out;
    }
}
