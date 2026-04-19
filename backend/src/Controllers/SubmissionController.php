<?php

namespace JutForm\Controllers;

use JutForm\Core\Database;
use JutForm\Core\QueueService;
use JutForm\Core\Request;
use JutForm\Core\RequestContext;
use JutForm\Core\Response;
use JutForm\Models\Form;
use JutForm\Models\KeyValueStore;
use JutForm\Models\Submission;

class SubmissionController
{
    public function index(Request $request, string $id): void
    {
        $sessionUserId = RequestContext::$currentUserId;
        if ($sessionUserId === null) {
            Response::error('Unauthorized', 401);
        }
        $form = Form::find((int) $id);
        if (!$form) {
            Response::error('Not found', 404);
        }
        if ((int) $form['user_id'] !== $sessionUserId) {
            $shared = KeyValueStore::get((int) $id, 'shared_with_user_ids');
            $ids = $shared ? json_decode($shared, true) : [];
            if (!is_array($ids) || !in_array($sessionUserId, $ids, true)) {
                Response::error('Forbidden', 403);
            }
        }


        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $offset = ($page - 1) * $limit;

        $rows = Submission::findByForm((int) $id, $limit, $offset);

        // Sidebar: the viewer's most recently updated forms, surfaced next to
        // the submissions table so they can jump between forms without going
        // back to the dashboard.
        $pdo = Database::getInstance();
        $related = [];
        $ctx = RequestContext::$currentUserId;
        if ($ctx !== null) {
            $stmt = $pdo->prepare('SELECT id, title FROM forms WHERE user_id = ? ORDER BY updated_at DESC LIMIT 20');
            $stmt->execute([$ctx]);
            $related = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        Response::json([
            'submissions' => $rows,
            'page' => $page,
            'limit' => $limit,
            'related_forms' => $related,
        ]);
    }

    public function create(Request $request, string $id): void
    {
        $form = Form::find((int) $id);
        if (!$form) {
            Response::error('Not found', 404);
        }
        $body = $request->jsonBody();
        $dataJson = isset($body['data']) ? json_encode($body['data'], JSON_UNESCAPED_UNICODE) : '{}';
        $sid = Submission::create((int) $id, $dataJson, $request->ip());
        QueueService::dispatch('submission_notify', [
            'form_id' => (int) $id,
            'submission_id' => $sid,
        ]);
        Response::json(['id' => $sid], 201);
    }

    public function exportCsv(Request $request, string $id): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $form = Form::find((int) $id);
        if (!$form || (int) $form['user_id'] !== $uid) {
            Response::error('Not found', 404);
        }
        $rows = Submission::findByForm((int) $id, 5000, 0);
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['id', 'submitted_at', 'data_json']);
        foreach ($rows as $r) {
            fputcsv($fh, [$r['id'], $r['submitted_at'], $r['data_json']]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        Response::csv('form-' . $id . '-submissions.csv', $csv);
    }
}
