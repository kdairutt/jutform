<?php

declare(strict_types=1);

/**
 * Integration test bootstrap: session, autoload, test mode response capture.
 */

define('JUTFORM_TESTING', true);

$backendRoot = dirname(__DIR__);
define('JUTFORM_BACKEND_ROOT', $backendRoot);

require_once $backendRoot . '/vendor/autoload.php';

if (getenv('DB_HOST') === false) {
    putenv('DB_HOST=127.0.0.1');
}
if (getenv('DB_PORT') === false) {
    putenv('DB_PORT=3307');
}
if (getenv('DB_NAME') === false) {
    putenv('DB_NAME=jutform');
}
if (getenv('DB_USER') === false) {
    putenv('DB_USER=jutform');
}
if (getenv('DB_PASS') === false) {
    putenv('DB_PASS=jutform_secret');
}

$sessionPath = sys_get_temp_dir() . '/jutform-phpunit-sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);

session_name('jutform_sid');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
