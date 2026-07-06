<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This migration can only be run from the command line.');
}

require_once __DIR__ . '/../../config/database.php';

$pdo = db();

$statements = [];

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    organization_type VARCHAR(100) NULL,
    website_url VARCHAR(2048) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY organizations_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS jurisdictions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    jurisdiction_type VARCHAR(50) NOT NULL,
    state_code CHAR(2) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT jurisdictions_parent_fk
        FOREIGN KEY (parent_id)
        REFERENCES jurisdictions(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    project_category VARCHAR(100) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS topics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS age_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    minimum_age INT UNSIGNED NULL,
    maximum_age INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS sources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NULL,
    title VARCHAR(500) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    source_type VARCHAR(100) NOT NULL,
    publication_version VARCHAR(255) NULL,
    date_checked DATE NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'needs_review',
    visibility VARCHAR(50) NOT NULL DEFAULT 'internal',
    public_summary TEXT NULL,
    what_establishes TEXT NULL,
    important_language TEXT NULL,
    saved_copy_path VARCHAR(2048) NULL,
    internal_notes LONGTEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,

    KEY sources_status_index (status),
    KEY sources_visibility_index (visibility),
    KEY sources_date_checked_index (date_checked),

    CONSTRAINT sources_organization_fk
        FOREIGN KEY (organization_id)
        REFERENCES organizations(id)
        ON DELETE SET NULL,

    CONSTRAINT sources_created_by_fk
        FOREIGN KEY (created_by)
        REFERENCES admin_users(id)
        ON DELETE SET NULL,

    CONSTRAINT sources_updated_by_fk
        FOREIGN KEY (updated_by)
        REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS source_jurisdictions (
    source_id BIGINT UNSIGNED NOT NULL,
    jurisdiction_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (source_id, jurisdiction_id),

    CONSTRAINT source_jurisdictions_source_fk
        FOREIGN KEY (source_id)
        REFERENCES sources(id)
        ON DELETE CASCADE,

    CONSTRAINT source_jurisdictions_jurisdiction_fk
        FOREIGN KEY (jurisdiction_id)
        REFERENCES jurisdictions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS source_projects (
    source_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (source_id, project_id),

    CONSTRAINT source_projects_source_fk
        FOREIGN KEY (source_id)
        REFERENCES sources(id)
        ON DELETE CASCADE,

    CONSTRAINT source_projects_project_fk
        FOREIGN KEY (project_id)
        REFERENCES projects(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS source_topics (
    source_id BIGINT UNSIGNED NOT NULL,
    topic_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (source_id, topic_id),

    CONSTRAINT source_topics_source_fk
        FOREIGN KEY (source_id)
        REFERENCES sources(id)
        ON DELETE CASCADE,

    CONSTRAINT source_topics_topic_fk
        FOREIGN KEY (topic_id)
        REFERENCES topics(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS source_age_groups (
    source_id BIGINT UNSIGNED NOT NULL,
    age_group_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (source_id, age_group_id),

    CONSTRAINT source_age_groups_source_fk
        FOREIGN KEY (source_id)
        REFERENCES sources(id)
        ON DELETE CASCADE,

    CONSTRAINT source_age_groups_age_group_fk
        FOREIGN KEY (age_group_id)
        REFERENCES age_groups(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$statements[] = <<<'SQL'
CREATE TABLE IF NOT EXISTS source_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    snapshot JSON NOT NULL,
    change_summary TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY source_versions_number_unique (
        source_id,
        version_number
    ),

    CONSTRAINT source_versions_source_fk
        FOREIGN KEY (source_id)
        REFERENCES sources(id)
        ON DELETE CASCADE,

    CONSTRAINT source_versions_created_by_fk
        FOREIGN KEY (created_by)
        REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

try {
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $migrationName = '001_create_source_system';

    $recordMigration = $pdo->prepare(
        'INSERT IGNORE INTO schema_migrations (migration)
         VALUES (:migration)'
    );

    $recordMigration->execute([
        'migration' => $migrationName,
    ]);

    echo "Source database tables created successfully.\n";
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Migration failed: {$exception->getMessage()}\n"
    );

    exit(1);
}