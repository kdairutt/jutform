<?php

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \JutForm\Core\Database::getInstance();

$pdo->exec("
    ALTER TABLE scheduled_emails
    MODIFY COLUMN status ENUM('pending','processing','sent','failed') DEFAULT 'pending'
");
