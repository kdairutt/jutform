<?php

namespace JutForm\Middleware;

use JutForm\Core\MiddlewareInterface;
use JutForm\Core\RedisClient;
use JutForm\Core\Request;

class CacheMiddleware implements MiddlewareInterface
{
    private const TTL = 300; // 5 minutes

    public function handle(Request $request, callable $next): void
    {
        $redis = RedisClient::getInstance();
        $key = 'cache:' . $request->path();

        $cached = $redis->get($key);
        if ($cached !== false && $cached !== null) {
            http_response_code(200);
            header('Content-Type: application/json; charset=UTF-8');
            echo $cached;
            exit;
        }

        // Cache miss: buffer the response via a callback so we can store it
        // in Redis as it is being flushed — this fires even when exit() is called.
        $ttl = self::TTL;
        ob_start(static function (string $buffer) use ($redis, $key, $ttl): string {
            if ($buffer !== '') {
                $redis->setex($key, $ttl, $buffer);
            }
            return $buffer;
        });

        // In test mode Response throws ResponseHalted instead of calling exit.
        // Clean up the buffer and re-throw so the test framework stays intact.
        try {
            $next();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
