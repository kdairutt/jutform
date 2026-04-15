<?php

declare(strict_types=1);

namespace JutForm\Tests;

use JutForm\Tests\Support\IntegrationTestCase;

final class AuthTest extends IntegrationTestCase
{
    public function testLoginSuccess(): void
    {
        $res = $this->postJson('/api/auth/login', [
            'username' => 'poweruser',
            'password' => 'password',
        ]);
        $this->assertSame('json', $res['type']);
        $this->assertSame(200, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('user', $body);
        $this->assertSame('poweruser', $body['user']['username']);
    }

    public function testLoginMissingCredentials(): void
    {
        $res = $this->postJson('/api/auth/login', []);
        $this->assertSame(400, $res['status']);
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('error', $body);
    }

    public function testLoginInvalidCredentials(): void
    {
        $res = $this->postJson('/api/auth/login', [
            'username' => 'poweruser',
            'password' => 'wrong',
        ]);
        $this->assertSame(401, $res['status']);
    }

    public function testProfileRequiresAuth(): void
    {
        $res = $this->get('/api/user/profile');
        $this->assertSame(401, $res['status']);
    }

    public function testProfileAfterLogin(): void
    {
        $this->loginAs('alice');
        $res = $this->get('/api/user/profile');
        $body = $this->jsonBody($res);
        $this->assertSame(200, $res['status']);
        $this->assertSame('alice', $body['user']['username']);
        $this->assertArrayNotHasKey('password_hash', $body['user']);
    }

    public function testLogoutClearsSession(): void
    {
        $this->loginAs('alice');
        $this->logout();
        $res = $this->get('/api/user/profile');
        $this->assertSame(401, $res['status']);
    }
}
