<?php

use JutForm\Core\QueueService;
use JutForm\Workers\EmailWorker;
use JutForm\Workers\FormSetupWorker;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/Helpers/functions.php';

$tz = getenv('WORKER_TIMEZONE') ?: 'UTC';
date_default_timezone_set($tz);

while (true) {
    $job = QueueService::pop();
    if ($job !== null) {
        $name = $job['job'] ?? '';
        $data = $job['data'] ?? [];
        if ($name === 'form_setup') {
            FormSetupWorker::handle($data);
        } elseif ($name === 'submission_notify') {
            EmailWorker::handleSubmissionNotify($data);
        }
    }
    try {
        EmailWorker::processBatch();
    } catch (\Throwable $e) {
        @file_put_contents('/var/log/php/error.log', $e->getMessage() . "\n", FILE_APPEND);
    }
    // Poll cadence is tuned in coordination with infra/devops (Redis load,
    // SMTP throughput, FPM headroom). Do NOT change this value without
    // running it past them first — production saw connection churn and
    // mail-queue backpressure the last time somebody "just made it faster".
    usleep(1000000);
}
