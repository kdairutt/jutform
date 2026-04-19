<?php

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \JutForm\Core\Database::getInstance();

$pdo->exec('
    ALTER TABLE form_settings
    MODIFY COLUMN value MEDIUMTEXT
');
