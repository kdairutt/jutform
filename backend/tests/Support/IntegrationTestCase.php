<?php

declare(strict_types=1);

namespace JutForm\Tests\Support;

use JutForm\Core\Database;
use JutForm\Core\Request;
use JutForm\Core\RequestContext;
use JutForm\Core\ResponseHalted;
use JutForm\Core\Router;
use JutForm\Core\TestResponseBuffer;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    private static bool $databaseAvailable = false;

    private static bool $databaseChecked = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        try {
            $config = require JUTFORM_BACKEND_ROOT . '/config/database.php';
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->query('SELECT 1');
            self::$databaseAvailable = true;
        } catch (\Throwable) {
            self::$databaseAvailable = false;
        }
        self::$databaseChecked = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (self::$databaseChecked && !self::$databaseAvailable) {
            self::markTestSkipped('MySQL not reachable; seed the DB and set DB_HOST/DB_PORT (e.g. 127.0.0.1:3307).');
        }
        Database::resetForTesting();
        $pdo = Database::getInstance();
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
        $_SESSION = [];
        $_FILES = [];
        RequestContext::$currentUserId = null;
        TestResponseBuffer::reset();
    }

    protected function tearDown(): void
    {
        if (self::$databaseChecked && self::$databaseAvailable) {
            $pdo = Database::getInstance();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Database::resetForTesting();
        }
        $_FILES = [];
        parent::tearDown();
    }

    /**
     * Dispatches one HTTP request through the same router wiring as {@see public/index.php}.
     *
     * @return array<string, mixed> Captured response payload (see TestResponseBuffer)
     */
    protected function dispatch(Request $request): array
    {
        TestResponseBuffer::reset();
        RequestContext::$currentUserId = null;
        $router = new Router();
        $register = require JUTFORM_BACKEND_ROOT . '/config/routes.php';
        $register($router);
        try {
            $router->dispatch($request);
            $this->fail('Expected application to send a response');
        } catch (ResponseHalted) {
            $last = TestResponseBuffer::$last;
            $this->assertNotNull($last, 'Response buffer should contain captured output');
            return $last;
        }
    }

    /**
     * @param array<string, mixed>|array<int|string, mixed> $data
     */
    protected function postJson(string $path, array $data): array
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $req = Request::create('POST', $path, [], $body, [
            'Content-Type' => 'application/json',
        ]);
        return $this->dispatch($req);
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     */
    protected function get(string $path, array $query = [], array $serverExtra = []): array
    {
        $req = Request::create('GET', $path, $query, null, [], $serverExtra);
        return $this->dispatch($req);
    }

    /**
     * @param array<string, mixed>|array<int|string, mixed> $data
     */
    protected function putJson(string $path, array $data): array
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $req = Request::create('PUT', $path, [], $body, [
            'Content-Type' => 'application/json',
        ]);
        return $this->dispatch($req);
    }

    protected function loginAs(string $username, string $password = 'password'): void
    {
        $res = $this->postJson('/api/auth/login', [
            'username' => $username,
            'password' => $password,
        ]);
        $this->assertSame('json', $res['type']);
        $this->assertSame(200, $res['status']);
        $body = $res['body'];
        $this->assertIsArray($body);
        $this->assertArrayHasKey('user', $body);
    }

    protected function logout(): void
    {
        $res = $this->postJson('/api/auth/logout', []);
        $this->assertSame('json', $res['type']);
        $this->assertSame(200, $res['status']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonBody(array $captured): array
    {
        $this->assertSame('json', $captured['type']);
        $this->assertIsArray($captured['body']);
        /** @var array<string, mixed> $b */
        $b = $captured['body'];
        return $b;
    }

}
