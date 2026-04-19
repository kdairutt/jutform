<?php

namespace JutForm\Controllers;

use JutForm\Core\Database;
use JutForm\Core\Request;
use JutForm\Core\RequestContext;
use JutForm\Core\Response;

class SearchController
{
    public function search(Request $request): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            Response::json(['results' => []]);
        }
        $pdo = Database::getInstance();
        // FULLTEXT BOOLEAN MODE: append * for prefix matching so short terms
        // like "for" still match "form". Leading-wildcard LIKE was doing a
        // full scan of 3.8 M rows; MATCH AGAINST uses the ft index.
        $ftTerm = '+' . implode('* +', preg_split('/\s+/', $term)) . '*';
        $stmt = $pdo->prepare(
            'SELECT id, config_key, value FROM app_config
             WHERE MATCH(value) AGAINST (? IN BOOLEAN MODE) LIMIT 200'
        );
        $stmt->execute([$ftTerm]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        Response::json(['results' => $rows]);
    }

    public function advancedSearch(Request $request): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $allowedFields = ['title', 'description', 'status'];
        $field = (string) $request->query('field', 'title');
        if (!in_array($field, $allowedFields, true)) {
            Response::error('Invalid field parameter', 400);
        }
        $term = (string) $request->query('term', '');
        $pdo = Database::getInstance();
        $sql = "SELECT * FROM forms WHERE {$field} LIKE ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['%' . $term . '%', (int) $uid]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        Response::json(['forms' => $rows]);
    }
}
