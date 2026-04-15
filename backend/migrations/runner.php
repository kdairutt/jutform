<?php

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = \JutForm\Core\Database::getInstance();

$files = glob(__DIR__ . '/[0-9]*.php');
sort($files);

$applied = 0;

foreach ($files as $file) {
    $version = basename($file);

    $count = (int) $pdo->query(
        'SELECT COUNT(*) FROM schema_migrations WHERE version = ' . $pdo->quote($version)
    )->fetchColumn();

    if ($count > 0) {
        echo "  skip  $version\n";
        continue;
    }

    echo "Applying $version...\n";
    require $file;
    $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, NOW())')
        ->execute([$version]);
    echo "  done  $version\n";
    $applied++;
}

if ($applied === 0) {
    echo "Nothing to apply — database is up to date.\n";
} else {
    echo "$applied migration(s) applied.\n";
}
