<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

$pdo = db();

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );

    $statement->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

try {
    /*
     * A fair is the continuing event:
     * Ionia Free Fair, Kent County Youth Fair, etc.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS fairs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            jurisdiction_id BIGINT UNSIGNED NULL,
            organization_id BIGINT UNSIGNED NULL,
            timezone VARCHAR(100) NOT NULL DEFAULT 'America/Detroit',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY uq_fairs_slug (slug),
            KEY idx_fairs_jurisdiction (jurisdiction_id),
            KEY idx_fairs_organization (organization_id),
            KEY idx_fairs_active (is_active)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * Each annual fair is its own cycle.
     * A source can be extended to another cycle without creating a new source.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS fair_cycles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            fair_id BIGINT UNSIGNED NOT NULL,
            cycle_year SMALLINT UNSIGNED NOT NULL,
            label VARCHAR(190) NOT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'upcoming',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY uq_fair_cycles_fair_year (fair_id, cycle_year),
            KEY idx_fair_cycles_status (status),
            KEY idx_fair_cycles_dates (start_date, end_date)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * Sources receive one primary category.
     * Existing source_topics can still hold additional topics.
     */
    if (!columnExists($pdo, 'sources', 'primary_topic_id')) {
        $pdo->exec(
            "ALTER TABLE sources
             ADD COLUMN primary_topic_id BIGINT UNSIGNED NULL"
        );
    }

    /*
     * Records that an unchanged source version was confirmed for a fair cycle.
     *
     * Example:
     * 2019 background-check document
     * confirmed as applicable to the 2026 Ionia fair cycle.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS source_applicability (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_id BIGINT UNSIGNED NOT NULL,
            source_version_id BIGINT UNSIGNED NULL,
            fair_cycle_id BIGINT UNSIGNED NOT NULL,

            status VARCHAR(40) NOT NULL DEFAULT 'current',
            verification_method VARCHAR(80) NULL,
            confirmed_by_source_id BIGINT UNSIGNED NULL,

            verified_at DATETIME NULL,
            verified_by_admin_user_id BIGINT UNSIGNED NULL,

            valid_until DATE NULL,
            expires_at_fair_end TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY uq_source_applicability_cycle (
                source_id,
                source_version_id,
                fair_cycle_id
            ),
            KEY idx_source_applicability_source (source_id),
            KEY idx_source_applicability_version (source_version_id),
            KEY idx_source_applicability_cycle (fair_cycle_id),
            KEY idx_source_applicability_status (status)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * One clean, reusable factual statement.
     *
     * Duplicate claims are not deleted during a merge.
     * They are marked merged and point to the surviving claim.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS claims (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            claim_text TEXT NOT NULL,
            normalized_text VARCHAR(500) NULL,

            primary_topic_id BIGINT UNSIGNED NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'needs_review',

            merged_into_claim_id BIGINT UNSIGNED NULL,
            internal_notes TEXT NULL,

            created_by_admin_user_id BIGINT UNSIGNED NULL,
            updated_by_admin_user_id BIGINT UNSIGNED NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY idx_claims_primary_topic (primary_topic_id),
            KEY idx_claims_status (status),
            KEY idx_claims_merged_into (merged_into_claim_id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * One submission from the bulk claim-entry form.
     *
     * The source, citation information, excerpt, location, and fair cycle
     * are entered once. Every claim separated by a blank line links to
     * this same evidence batch.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS evidence_batches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            source_id BIGINT UNSIGNED NOT NULL,
            source_version_id BIGINT UNSIGNED NULL,
            fair_cycle_id BIGINT UNSIGNED NULL,

            batch_label VARCHAR(190) NULL,
            evidence_type VARCHAR(60) NOT NULL DEFAULT 'published_source',

            source_excerpt LONGTEXT NULL,
            source_location VARCHAR(500) NULL,

            verification_method VARCHAR(80) NULL,
            applies_from DATE NULL,
            applies_until DATE NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 1,

            notes TEXT NULL,
            created_by_admin_user_id BIGINT UNSIGNED NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY idx_evidence_batches_source (source_id),
            KEY idx_evidence_batches_version (source_version_id),
            KEY idx_evidence_batches_cycle (fair_cycle_id),
            KEY idx_evidence_batches_current (is_current)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * Many-to-many evidence:
     *
     * One source/evidence batch can support many claims.
     * One claim can be supported by many source/evidence batches.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS claim_evidence (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            claim_id BIGINT UNSIGNED NOT NULL,
            evidence_batch_id BIGINT UNSIGNED NOT NULL,

            relationship_type VARCHAR(40) NOT NULL DEFAULT 'supports',
            is_primary TINYINT(1) NOT NULL DEFAULT 0,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY uq_claim_evidence_relationship (
                claim_id,
                evidence_batch_id,
                relationship_type
            ),
            KEY idx_claim_evidence_claim (claim_id),
            KEY idx_claim_evidence_batch (evidence_batch_id),
            KEY idx_claim_evidence_type (relationship_type)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * Claims can have additional categories beyond their primary category.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS claim_topics (
            claim_id BIGINT UNSIGNED NOT NULL,
            topic_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (claim_id, topic_id),
            KEY idx_claim_topics_topic (topic_id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * Expandable scope: county, state, project, age group, and fair cycle.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS claim_jurisdictions (
            claim_id BIGINT UNSIGNED NOT NULL,
            jurisdiction_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (claim_id, jurisdiction_id),
            KEY idx_claim_jurisdictions_jurisdiction (jurisdiction_id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS claim_projects (
            claim_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (claim_id, project_id),
            KEY idx_claim_projects_project (project_id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS claim_age_groups (
            claim_id BIGINT UNSIGNED NOT NULL,
            age_group_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (claim_id, age_group_id),
            KEY idx_claim_age_groups_age_group (age_group_id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS claim_fair_cycles (
            claim_id BIGINT UNSIGNED NOT NULL,
            fair_cycle_id BIGINT UNSIGNED NOT NULL,
            applicability_status VARCHAR(40) NOT NULL DEFAULT 'current',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (claim_id, fair_cycle_id),
            KEY idx_claim_fair_cycles_cycle (fair_cycle_id),
            KEY idx_claim_fair_cycles_status (applicability_status)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * Preserves a permanent audit trail when duplicate claims are merged.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS claim_merge_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            canonical_claim_id BIGINT UNSIGNED NOT NULL,
            merged_claim_id BIGINT UNSIGNED NOT NULL,
            merged_by_admin_user_id BIGINT UNSIGNED NULL,
            merge_notes TEXT NULL,
            merged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY uq_claim_merge_history_merged (merged_claim_id),
            KEY idx_claim_merge_history_canonical (canonical_claim_id)
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    echo "Claim system database foundation created successfully." . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Migration failed: " . $exception->getMessage() . PHP_EOL
    );

    exit(1);
}