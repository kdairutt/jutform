<?php

declare(strict_types=1);

namespace JutForm\Tests;

use JutForm\Core\Database;
use JutForm\Tests\Support\IntegrationTestCase;

final class WebhookTest extends IntegrationTestCase
{
    public function testCreateRequiresValidUrl(): void
    {
        $this->loginAs('poweruser');
        $res = $this->postJson('/api/forms/1/webhooks', [
            'url' => 'not-a-url',
        ]);
        $this->assertSame(400, $res['status']);
    }

    public function testCreateRejectsLocalUrl(): void
    {
        $this->loginAs('poweruser');
        $res = $this->postJson('/api/forms/1/webhooks', [
            'url' => 'http://127.0.0.1/hook',
        ]);
        $this->assertSame(400, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('error', $body);
    }

    public function testCreateSuccess(): void
    {
        $this->loginAs('poweruser');
        $res = $this->postJson('/api/forms/1/webhooks', [
            'url' => 'https://example.com/webhook',
            'events' => 'submission.created',
        ]);
        $this->assertSame(201, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('id', $body);
        $this->assertArrayHasKey('secret_token', $body);
    }

    public function testTestEndpointIsOwnerOnly(): void
    {
        $this->loginAs('poweruser');
        $created = $this->postJson('/api/forms/1/webhooks', [
            'url' => 'https://example.com/webhook',
            'events' => 'submission.created',
        ]);
        $createdBody = $this->jsonBody($created);
        $hookId = (int) $createdBody['id'];

        $_SESSION = [];
        $this->loginAs('bob');

        $res = $this->postJson('/api/webhooks/' . $hookId . '/test', []);
        $this->assertSame(403, $res['status']);
    }

    public function testOwnerCanTestWebhookAndGetResultEnvelope(): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO webhooks (form_id, url, events, is_active, secret_token, created_at, updated_at)
             VALUES (?, ?, ?, 1, ?, NOW(), NOW())'
        );
        $stmt->execute([1, 'http://127.0.0.1/hook', 'submission.created', 'secret']);
        $hookId = (int) $pdo->lastInsertId();

        $this->loginAs('poweruser');

        $res = $this->postJson('/api/webhooks/' . $hookId . '/test', []);
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('result', $body);
        $this->assertIsArray($body['result']);
        $this->assertSame(false, $body['result']['ok'] ?? null);
        $this->assertSame('local_urls_not_allowed', $body['result']['error'] ?? null);
    }
}
