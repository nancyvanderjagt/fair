<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = db();

    $version = $pdo
        ->query('SELECT VERSION()')
        ->fetchColumn();

    echo "Database connection successful.\n";
    echo "MySQL version: {$version}\n";
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Database connection failed: {$exception->getMessage()}\n"
    );

    exit(1);
}