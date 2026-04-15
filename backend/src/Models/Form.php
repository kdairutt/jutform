<?php

namespace JutForm\Models;

use JutForm\Core\Database;
use PDO;

class Form
{
    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM forms WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findByUser(int $userId): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM forms WHERE user_id = ? ORDER BY updated_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(array $data): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO forms (user_id, title, description, status, is_public, fields_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $now = $data['created_at'] ?? date('Y-m-d H:i:s');
        $stmt->execute([
            $data['user_id'],
            $data['title'],
            $data['description'] ?? null,
            $data['status'] ?? 'draft',
            $data['is_public'] ?? 0,
            $data['fields_json'] ?? '[]',
            $now,
            $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        foreach (['title', 'description', 'status', 'is_public', 'fields_json'] as $k) {
            if (array_key_exists($k, $data)) {
                $fields[] = "$k = ?";
                $params[] = $data[$k];
            }
        }
        if ($fields === []) {
            return;
        }
        $sql = 'UPDATE forms SET ' . implode(', ', $fields) . ', updated_at = ? WHERE id = ?';
        $params[] = date('Y-m-d H:i:s');
        $params[] = $id;
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
    }
}
