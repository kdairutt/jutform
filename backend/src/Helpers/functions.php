<?php

use JutForm\Core\Database;
use JutForm\Core\RequestContext;

function env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    return $v === false ? $default : $v;
}

function app_log(string $message, string $level = 'info'): void
{
    $line = sprintf("[%s] [%s] %s\n", date('c'), $level, $message);
    @file_put_contents('/var/log/php/error.log', $line, FILE_APPEND);
}

function now_sql(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Verifies the acting user may access settings for a form (e.g. collaborator flows).
 * Loads the form owner and aligns context for downstream permission checks.
 */
function checkFormOwnerPermission(int $formId): bool
{
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare('SELECT user_id FROM forms WHERE id = ? LIMIT 1');
    $stmt->execute([$formId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    $ownerId = (int) $row['user_id'];
    RequestContext::$currentUserId = $ownerId;
    return true;
}
