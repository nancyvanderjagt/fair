<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

$pdo = db();

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS research_questions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            question_text TEXT NOT NULL,
            normalized_text VARCHAR(500) NULL,

            primary_topic_id BIGINT UNSIGNED NULL,
            fair_cycle_id BIGINT UNSIGNED NULL,

            status VARCHAR(40) NOT NULL DEFAULT 'open',
            priority VARCHAR(30) NOT NULL DEFAULT 'normal',

            resolution_notes TEXT NULL,
            answered_by_claim_id BIGINT UNSIGNED NULL,

            created_by_admin_user_id BIGINT UNSIGNED NULL,
            updated_by_admin_user_id BIGINT UNSIGNED NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY idx_research_questions_topic (primary_topic_id),
            KEY idx_research_questions_cycle (fair_cycle_id),
            KEY idx_research_questions_status (status),
            KEY idx_research_questions_priority (priority),
            KEY idx_research_questions_answered_claim (
                answered_by_claim_id
            )
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    /*
     * A question can arise from one source and later be connected
     * to additional sources during investigation.
     */
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS research_question_sources (
            research_question_id BIGINT UNSIGNED NOT NULL,
            source_id BIGINT UNSIGNED NOT NULL,
            source_version_id BIGINT UNSIGNED NULL,

            relationship_type VARCHAR(40)
                NOT NULL DEFAULT 'raised_by',

            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (
                research_question_id,
                source_id,
                relationship_type
            ),

            KEY idx_question_sources_source (source_id),
            KEY idx_question_sources_version (source_version_id),
            KEY idx_question_sources_relationship (
                relationship_type
            )
        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    echo "Research question system created successfully." . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Migration failed: " . $exception->getMessage() . PHP_EOL
    );

    exit(1);
}