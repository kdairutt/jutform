<?php

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \JutForm\Core\Database::getInstance();

$pdo->exec("
    ALTER TABLE webhooks
    ADD COLUMN description VARCHAR(255) DEFAULT NULL AFTER url
");
