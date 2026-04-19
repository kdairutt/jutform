<?php

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \JutForm\Core\Database::getInstance();

$pdo->exec('ALTER TABLE app_config ADD FULLTEXT INDEX ft_app_config_value (value)');
