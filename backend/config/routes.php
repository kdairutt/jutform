<?php

use JutForm\Controllers\AdminController;
use JutForm\Controllers\EmailController;
use JutForm\Controllers\FeatureController;
use JutForm\Controllers\FileUploadController;
use JutForm\Controllers\FormController;
use JutForm\Controllers\ReferenceController;
use JutForm\Controllers\SearchController;
use JutForm\Controllers\SubmissionController;
use JutForm\Controllers\UserController;
use JutForm\Controllers\WebhookController;
use JutForm\Core\Router;
use JutForm\Middleware\AuthMiddleware;
use JutForm\Middleware\CacheMiddleware;
use JutForm\Middleware\RateLimitMiddleware;

return static function (Router $router): void {
    $router->post('/api/auth/login', [UserController::class, 'login']);
    $router->post('/api/auth/logout', [UserController::class, 'logout']);

    $router->get('/api/user/profile', [UserController::class, 'profile'], [AuthMiddleware::class]);

    $router->get('/api/forms/analytics', [FormController::class, 'analytics'], [AuthMiddleware::class]);
    $router->get('/api/forms', [FormController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/api/forms', [FormController::class, 'create'], [AuthMiddleware::class]);
    $router->get('/api/forms/{id}/edit', [FormController::class, 'edit'], [AuthMiddleware::class]);
    $router->get('/api/forms/{id}', [FormController::class, 'show'], [AuthMiddleware::class]);
    $router->put('/api/forms/{id}', [FormController::class, 'update'], [AuthMiddleware::class]);

    $router->get('/api/forms/{id}/submissions/export', [SubmissionController::class, 'exportCsv'], [AuthMiddleware::class]);
    $router->get('/api/forms/{id}/submissions', [SubmissionController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/api/forms/{id}/submissions', [SubmissionController::class, 'create'], [RateLimitMiddleware::class]);

    $router->get('/api/search', [SearchController::class, 'search'], [AuthMiddleware::class]);
    $router->get('/api/search/advanced', [SearchController::class, 'advancedSearch'], [AuthMiddleware::class]);

    $router->post('/api/forms/{id}/scheduled-emails', [EmailController::class, 'schedule'], [AuthMiddleware::class]);

    $router->post('/api/forms/{id}/webhooks', [WebhookController::class, 'create'], [AuthMiddleware::class]);
    $router->post('/api/webhooks/{id}/test', [WebhookController::class, 'test'], [AuthMiddleware::class]);

    $router->get('/api/admin/revenue', [AdminController::class, 'revenue'], [AuthMiddleware::class]);
    $router->get('/internal/admin/config', [AdminController::class, 'internalConfig']);
    $router->get('/admin/logs', [AdminController::class, 'logs'], [AuthMiddleware::class]);

    $router->get('/api/field-types', [ReferenceController::class, 'fieldTypes'], [CacheMiddleware::class]);
    $router->get('/api/countries', [ReferenceController::class, 'countries'], [CacheMiddleware::class]);

    $router->post('/api/forms/{id}/files', [FileUploadController::class, 'upload'], [AuthMiddleware::class]);
    $router->get('/api/files/{id}/download', [FileUploadController::class, 'download'], [AuthMiddleware::class]);
    $router->get('/api/forms/{id}/logo', [FileUploadController::class, 'logo']);

    $router->get('/api/forms/{id}/export/pdf', [FeatureController::class, 'exportPdf'], [AuthMiddleware::class]);
    $router->post('/api/payments', [FeatureController::class, 'createPayment'], [AuthMiddleware::class]);
    $router->get('/api/analytics/summary', [FeatureController::class, 'analyticsSummary'], [AuthMiddleware::class]);
};
