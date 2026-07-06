<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This migration can only be run from the command line.');
}

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = db();

    $pdo->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS tags (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
        SQL
    );

    $pdo->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS source_tags (
            source_id BIGINT UNSIGNED NOT NULL,
            tag_id BIGINT UNSIGNED NOT NULL,

            PRIMARY KEY (source_id, tag_id),

            CONSTRAINT source_tags_source_fk
                FOREIGN KEY (source_id)
                REFERENCES sources(id)
                ON DELETE CASCADE,

            CONSTRAINT source_tags_tag_fk
                FOREIGN KEY (tag_id)
                REFERENCES tags(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci
        SQL
    );

    $statement = $pdo->prepare(
        'INSERT IGNORE INTO schema_migrations (migration)
         VALUES (:migration)'
    );

    $statement->execute([
        'migration' => '003_add_source_tags',
    ]);

    echo "Source tag tables created successfully.\n";
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Migration failed: {$exception->getMessage()}\n"
    );

    exit(1);
}