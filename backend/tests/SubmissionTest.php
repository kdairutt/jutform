<?php

declare(strict_types=1);

namespace JutForm\Tests;

use JutForm\Core\Request;
use JutForm\Tests\Support\IntegrationTestCase;

final class SubmissionTest extends IntegrationTestCase
{
    public function testCreatePublicSubmission(): void
    {
        $body = json_encode(['data' => ['test_key' => 'test_value']], JSON_THROW_ON_ERROR);
        $req = Request::create('POST', '/api/forms/1/submissions', [], $body, [
            'Content-Type' => 'application/json',
        ]);
        $res = $this->dispatch($req);
        $this->assertSame(201, $res['status']);
        $payload = $this->jsonBody($res);
        $this->assertArrayHasKey('id', $payload);
    }

    public function testIndexRequiresOwnerOrSharedUser(): void
    {
        $this->loginAs('poweruser');
        $res = $this->get('/api/forms/1/submissions', ['page' => 1, 'limit' => 5]);
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('submissions', $body);
        $this->assertArrayHasKey('page', $body);
        $this->assertArrayHasKey('limit', $body);
        $this->assertArrayHasKey('related_forms', $body);
    }

    public function testIndexForbiddenForNonCollaborator(): void
    {
        $this->loginAs('bob');
        $res = $this->get('/api/forms/1/submissions');
        $this->assertSame(403, $res['status']);
    }

    public function testExportCsvRequiresAuth(): void
    {
        $res = $this->get('/api/forms/1/submissions/export');
        $this->assertSame(401, $res['status']);
    }

    public function testExportCsvOwnerReturnsAttachment(): void
    {
        $this->loginAs('poweruser');
        $res = $this->get('/api/forms/1/submissions/export');
        $this->assertSame('csv', $res['type']);
        $csv = (string) ($res['body'] ?? '');
        $this->assertStringContainsString('id', $csv);
        $this->assertStringContainsString('data_json', $csv);
    }
}
