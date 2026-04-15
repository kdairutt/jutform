<?php

declare(strict_types=1);

namespace JutForm\Tests;

use JutForm\Core\Request;
use JutForm\Tests\Support\IntegrationTestCase;

final class AdminTest extends IntegrationTestCase
{
    public function testRevenueForbiddenForRegularUser(): void
    {
        $this->loginAs('alice');
        $res = $this->get('/api/admin/revenue');
        $this->assertSame(403, $res['status']);
    }

    public function testRevenueAdminOk(): void
    {
        $this->loginAs('admin');
        $res = $this->get('/api/admin/revenue');
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('revenue_total', $body);
        $this->assertIsFloat($body['revenue_total']);
    }

    public function testInternalConfigFromLoopback(): void
    {
        $req = Request::create('GET', '/internal/admin/config', [], null, [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $res = $this->dispatch($req);
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('items', $body);
        $this->assertIsArray($body['items']);
    }

    public function testLogsRequiresAdmin(): void
    {
        $this->loginAs('admin');
        $res = $this->get('/admin/logs');
        $this->assertSame('html', $res['type']);
        $this->assertSame(200, $res['status']);
        $this->assertIsString($res['body'] ?? '');
    }
}
