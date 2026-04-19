<?php

namespace JutForm\Models;

use JutForm\Core\Database;
use PDO;

class Submission
{
    public static function findByForm(int $formId, int $limit, int $offset): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM submissions WHERE form_id = ? ORDER BY submitted_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $formId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function countByForm(int $formId): int
    {
        $stmt = Database::getInstance()->prepare('SELECT COUNT(*) AS c FROM submissions WHERE form_id = ?');
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['c'] ?? 0);
    }

    public static function getLatestSubmittedAt(int $formId): ?string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT MAX(submitted_at) AS m FROM submissions WHERE form_id = ?'
        );
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $m = $row['m'] ?? null;
        return $m !== null ? (string) $m : null;
    }

    public static function create(int $formId, string $dataJson, ?string $ip): int
    {
        $pdo = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO submissions (form_id, data_json, ip_address, submitted_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$formId, $dataJson, $ip, $now]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Returns submission count and latest submitted_at for each form in one query.
     * Result is keyed by form_id.
     *
     * @param int[] $formIds
     * @return array<int, array{count: int, latest: string|null}>
     */
    public static function statsForForms(array $formIds): array
    {
        if ($formIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($formIds), '?'));
        $stmt = Database::getInstance()->prepare(
            "SELECT form_id, COUNT(*) AS cnt, MAX(submitted_at) AS latest
             FROM submissions WHERE form_id IN ({$placeholders}) GROUP BY form_id"
        );
        $stmt->execute($formIds);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['form_id']] = [
                'count' => (int) $row['cnt'],
                'latest' => $row['latest'] !== null ? (string) $row['latest'] : null,
            ];
        }
        return $out;
    }

    public static function findForUserForms(int $userId, int $limit, int $offset): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT s.*, f.user_id AS form_owner_id, f.title AS form_title
             FROM submissions s
             INNER JOIN forms f ON f.id = s.form_id
             WHERE f.user_id = ?
             LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
