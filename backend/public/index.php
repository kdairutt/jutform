<?php
/**
 * JutForm application entry point.
 * All API and admin requests are routed through here via Nginx.
 */

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/vendor/autoload.php';

session_name('jutform_sid');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once ROOT_PATH . '/src/Helpers/functions.php';

use JutForm\Core\Router;
use JutForm\Core\Request;
use JutForm\Core\Database;

$request = Request::fromGlobals();
$db = Database::getInstance();

$router = new Router();
$registerRoutes = require ROOT_PATH . '/config/routes.php';
$registerRoutes($router);

$router->dispatch($request);
