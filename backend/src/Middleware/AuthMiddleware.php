<?php

namespace JutForm\Middleware;

use JutForm\Core\Database;
use JutForm\Core\MiddlewareInterface;
use JutForm\Core\Request;
use JutForm\Core\RequestContext;
use JutForm\Core\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null || $userId === '') {
            Response::error('Unauthorized', 401);
        }
        RequestContext::$currentUserId = (int) $userId;
        $next();
    }
}
