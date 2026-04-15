<?php

declare(strict_types=1);

namespace JutForm\Tests;

use JutForm\Tests\Support\IntegrationTestCase;

final class FormCrudTest extends IntegrationTestCase
{
    public function testCreateValidationRequiresTitle(): void
    {
        $this->loginAs('alice');
        $res = $this->postJson('/api/forms', ['description' => 'no title']);
        $this->assertSame(400, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('error', $body);
    }

    public function testCreateReturns201(): void
    {
        $this->loginAs('alice');
        $title = 'PHPUnit Form ' . bin2hex(random_bytes(4));
        $res = $this->postJson('/api/forms', [
            'title' => $title,
            'description' => 'integration',
            'status' => 'draft',
            'fields' => [],
        ]);
        $this->assertSame(201, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('id', $body);
        $this->assertArrayHasKey('status', $body);
        $this->assertSame('created', $body['status']);
    }

    public function testListReturnsFormsForCurrentUser(): void
    {
        $this->loginAs('poweruser');
        $res = $this->get('/api/forms');
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('forms', $body);
        $this->assertGreaterThan(0, \count($body['forms']));
        $first = $body['forms'][0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('submission_count', $first);
    }

    public function testShowOwnedForm(): void
    {
        $this->loginAs('poweruser');
        $res = $this->get('/api/forms/1');
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('form', $body);
        $this->assertArrayHasKey('settings', $body);
        $this->assertArrayHasKey('resources', $body);
        $this->assertSame(1, (int) $body['form']['id']);
    }

    public function testShowOtherUserFormNotFound(): void
    {
        $this->loginAs('bob');
        $res = $this->get('/api/forms/1');
        $this->assertSame(404, $res['status']);
    }

    public function testUpdateOwnedForm(): void
    {
        $this->loginAs('poweruser');
        $res = $this->putJson('/api/forms/1', [
            'title' => 'Power form 1',
            'settings' => ['theme_preference' => 'light'],
        ]);
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertTrue($body['ok'] ?? false);

        $show = $this->get('/api/forms/1');
        $payload = $this->jsonBody($show);
        $this->assertSame('light', $payload['settings']['theme_preference'] ?? '');
    }
}
