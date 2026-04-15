<?php

namespace JutForm\Controllers;

use JutForm\Core\Database;
use JutForm\Core\Request;
use JutForm\Core\RequestContext;
use JutForm\Core\Response;
use JutForm\Models\Form;
use JutForm\Services\WebhookService;

class WebhookController
{
    public function create(Request $request, string $id): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $form = Form::find((int) $id);
        if (!$form || (int) $form['user_id'] !== $uid) {
            Response::error('Not found', 404);
        }
        $body = $request->jsonBody();
        $url = (string) ($body['url'] ?? '');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            Response::error('Invalid url', 400);
        }
        if (\isLocalRequest($url)) {
            Response::error('URL not allowed', 400);
        }
        $method = strtoupper((string) ($body['method'] ?? 'POST'));
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        if (!in_array($method, $allowedMethods, true)) {
            Response::error('Invalid method', 400);
        }
        $pdo = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO webhooks (form_id, url, method, events, is_active, secret_token, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, ?, ?)'
        );
        $secret = bin2hex(random_bytes(8));
        $stmt->execute([
            (int) $id,
            $url,
            $method,
            $body['events'] ?? 'submission.created',
            $secret,
            $now,
            $now,
        ]);
        Response::json(['id' => (int) $pdo->lastInsertId(), 'secret_token' => $secret], 201);
    }

    public function test(Request $request, string $id): void
    {
        $uid = RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM webhooks WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $id]);
        $hook = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$hook) {
            Response::error('Not found', 404);
        }
        $form = Form::find((int) $hook['form_id']);
        if (!$form || (int) $form['user_id'] !== $uid) {
            Response::error('Forbidden', 403);
        }
        $method = (string) ($hook['method'] ?? 'POST');
        $res = WebhookService::fire($hook['url'], ['event' => 'ping', 'form_id' => (int) $form['id']], $method);
        Response::json(['result' => $res]);
    }
}
