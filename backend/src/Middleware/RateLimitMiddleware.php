<?php

namespace JutForm\Middleware;

use JutForm\Core\MiddlewareInterface;
use JutForm\Core\RedisClient;
use JutForm\Core\Request;
use JutForm\Core\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    private const LIMIT = 10;
    private const WINDOW = 5;

    public function handle(Request $request, callable $next): void
    {
        $ip = $request->ip() ?: 'unknown';
        $window = intdiv(time(), self::WINDOW);
        $key = 'rl:sub:' . $ip . ':' . $window;

        $redis = RedisClient::getInstance();
        $count = $redis->incr($key);
        if ($count === 1) {
            // First hit in this window — set TTL to window duration + 1s buffer.
            $redis->expire($key, self::WINDOW + 1);
        }

        if ($count > self::LIMIT) {
            $retryAfter = self::WINDOW - (time() % self::WINDOW);
            header('Retry-After: ' . $retryAfter);
            Response::error('Too Many Requests', 429);
        }

        $next();
    }
}
