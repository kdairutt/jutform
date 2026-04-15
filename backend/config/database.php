<?php

return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'jutform',
    'username' => getenv('DB_USER') ?: 'jutform',
    'password' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8',
];
