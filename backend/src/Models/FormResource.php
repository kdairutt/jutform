<?php

namespace JutForm\Models;

use JutForm\Core\Database;
use PDO;

class FormResource
{
    public static function forForm(int $formId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM form_resources WHERE form_id = ?'
        );
        $stmt->execute([$formId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function ensureDefaults(int $formId): void
    {
        $pdo = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $types = ['notifications', 'theme', 'counter'];
        foreach ($types as $t) {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO form_resources (form_id, resource_type, resource_data, created_at)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$formId, $t, '{}', $now]);
        }
    }
}
