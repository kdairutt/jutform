<?php

declare(strict_types=1);

namespace JutForm\Tests;

use JutForm\Tests\Support\IntegrationTestCase;

/**
 * Download path only; {@see is_uploaded_file} prevents realistic upload tests in CLI without HTTP.
 */
final class FileDownloadTest extends IntegrationTestCase
{
    public function testUploadRequiresAuth(): void
    {
        $res = $this->postJson('/api/forms/1/files', []);
        $this->assertSame(401, $res['status']);
    }

    public function testUploadRejectsMissingFile(): void
    {
        $this->loginAs('poweruser');
        $res = $this->postJson('/api/forms/1/files', []);
        $this->assertSame(400, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertSame('file required', $body['error'] ?? null);
    }

    public function testDownloadRequiresAuth(): void
    {
        $res = $this->get('/api/files/1/download');
        $this->assertSame(401, $res['status']);
    }

    public function testOwnerCanDownloadSeedFile(): void
    {
        $this->loginAs('poweruser');
        $res = $this->get('/api/files/1/download');
        $this->assertSame('file', $res['type']);
        $this->assertSame(200, $res['status']);
        $this->assertSame('logo.png', $res['downloadName']);
        $this->assertStringEndsWith('logo.png', (string) ($res['path'] ?? ''));
    }

    public function testNonOwnerGetsNotFound(): void
    {
        $this->loginAs('bob');
        $res = $this->get('/api/files/1/download');
        $this->assertSame(404, $res['status']);
    }
}
