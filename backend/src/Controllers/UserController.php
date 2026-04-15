<?php

namespace JutForm\Controllers;

use JutForm\Core\Request;
use JutForm\Core\Response;
use JutForm\Models\User;

class UserController
{
    public function login(Request $request): void
    {
        $body = $request->jsonBody();
        $username = (string) ($body['username'] ?? '');
        $password = (string) ($body['password'] ?? '');
        if ($username === '' || $password === '') {
            Response::error('username and password required', 400);
        }
        $user = User::findByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::error('Invalid credentials', 401);
        }
        $_SESSION['user_id'] = (int) $user['id'];
        Response::json([
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
                'role' => $user['role'],
            ],
        ]);
    }

    public function logout(Request $request): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        Response::json(['ok' => true]);
    }

    public function profile(Request $request): void
    {
        $uid = \JutForm\Core\RequestContext::$currentUserId;
        if ($uid === null) {
            Response::error('Unauthorized', 401);
        }
        $user = User::find($uid);
        if (!$user) {
            Response::error('User not found', 404);
        }
        unset($user['password_hash']);
        Response::json(['user' => $user]);
    }
}
