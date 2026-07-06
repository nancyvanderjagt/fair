<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This migration can only be run from the command line.');
}

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = db();

    $columnExists = $pdo->query(
        "SHOW COLUMNS FROM sources LIKE 'related_site_pages'"
    )->fetch();

    if ($columnExists === false) {
        $pdo->exec(
            'ALTER TABLE sources
             ADD COLUMN related_site_pages TEXT NULL
             AFTER important_language'
        );
    }

    $statement = $pdo->prepare(
        'INSERT IGNORE INTO schema_migrations (migration)
         VALUES (:migration)'
    );

    $statement->execute([
        'migration' => '002_add_related_site_pages',
    ]);

    echo "Related site pages field added successfully.\n";
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Migration failed: {$exception->getMessage()}\n"
    );

    exit(1);
}