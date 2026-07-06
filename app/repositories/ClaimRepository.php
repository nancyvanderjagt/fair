<?php

declare(strict_types=1);

final class ClaimRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findSource(int $sourceId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                s.*,
                o.name AS organization_name,
                t.name AS primary_topic_name
             FROM sources s
             LEFT JOIN organizations o
                ON o.id = s.organization_id
             LEFT JOIN topics t
                ON t.id = s.primary_topic_id
             WHERE s.id = :source_id
             LIMIT 1"
        );

        $statement->execute(['source_id' => $sourceId]);

        $source = $statement->fetch(PDO::FETCH_ASSOC);

        return $source !== false ? $source : null;
    }

    public function getFairCycles(): array
    {
        $statement = $this->pdo->query(
            "SELECT
                fc.id,
                fc.cycle_year,
                fc.label,
                fc.start_date,
                fc.end_date,
                fc.status,
                f.name AS fair_name
             FROM fair_cycles fc
             INNER JOIN fairs f
                ON f.id = fc.fair_id
             ORDER BY
                CASE fc.status
                    WHEN 'active' THEN 1
                    WHEN 'upcoming' THEN 2
                    ELSE 3
                END,
                fc.cycle_year DESC,
                f.name"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClaimsForSource(int $sourceId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT
                c.id,
                c.claim_text,
                c.status,
                c.created_at,
                t.name AS category_name,

                (
                    SELECT COUNT(DISTINCT eb2.source_id)
                    FROM claim_evidence ce2
                    INNER JOIN evidence_batches eb2
                        ON eb2.id = ce2.evidence_batch_id
                    WHERE ce2.claim_id = c.id
                      AND ce2.relationship_type = 'supports'
                      AND eb2.is_current = 1
                ) AS current_source_count

             FROM claims c

             INNER JOIN claim_evidence ce
                ON ce.claim_id = c.id

             INNER JOIN evidence_batches eb
                ON eb.id = ce.evidence_batch_id

             LEFT JOIN topics t
                ON t.id = c.primary_topic_id

             WHERE eb.source_id = :source_id
               AND c.merged_into_claim_id IS NULL

             ORDER BY c.created_at DESC, c.id DESC"
        );

        $statement->execute(['source_id' => $sourceId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createClaimsFromSource(
        int $sourceId,
        string $rawClaims,
        array $evidence,
        ?int $adminUserId = null
    ): int {
        $claims = $this->splitClaims($rawClaims);

        if ($claims === []) {
            throw new InvalidArgumentException(
                'Enter at least one claim. Separate claims with a blank line.'
            );
        }

        $source = $this->findSource($sourceId);

        if ($source === null) {
            throw new RuntimeException('The selected source could not be found.');
        }

        $sourceVersionId = $this->findLatestSourceVersionId($sourceId);
        $primaryTopicId = $this->findSourcePrimaryTopicId($sourceId);

        $fairCycleId = isset($evidence['fair_cycle_id'])
            && (int) $evidence['fair_cycle_id'] > 0
                ? (int) $evidence['fair_cycle_id']
                : null;

        $this->pdo->beginTransaction();

        try {
            $batchStatement = $this->pdo->prepare(
                "INSERT INTO evidence_batches (
                    source_id,
                    source_version_id,
                    fair_cycle_id,
                    batch_label,
                    evidence_type,
                    source_excerpt,
                    source_location,
                    verification_method,
                    is_current,
                    created_by_admin_user_id
                ) VALUES (
                    :source_id,
                    :source_version_id,
                    :fair_cycle_id,
                    :batch_label,
                    'published_source',
                    :source_excerpt,
                    :source_location,
                    :verification_method,
                    1,
                    :created_by_admin_user_id
                )"
            );

            $batchStatement->execute([
                'source_id' => $sourceId,
                'source_version_id' => $sourceVersionId,
                'fair_cycle_id' => $fairCycleId,
                'batch_label' => $evidence['batch_label'] ?: null,
                'source_excerpt' => $evidence['source_excerpt'] ?: null,
                'source_location' => $evidence['source_location'] ?: null,
                'verification_method' =>
                    $evidence['verification_method'] ?: 'manual_review',
                'created_by_admin_user_id' => $adminUserId,
            ]);

            $evidenceBatchId = (int) $this->pdo->lastInsertId();

            $claimStatement = $this->pdo->prepare(
                "INSERT INTO claims (
                    claim_text,
                    normalized_text,
                    primary_topic_id,
                    status,
                    created_by_admin_user_id,
                    updated_by_admin_user_id
                ) VALUES (
                    :claim_text,
                    :normalized_text,
                    :primary_topic_id,
                    'needs_review',
                    :created_by_admin_user_id,
                    :updated_by_admin_user_id
                )"
            );

            $evidenceStatement = $this->pdo->prepare(
                "INSERT INTO claim_evidence (
                    claim_id,
                    evidence_batch_id,
                    relationship_type,
                    is_primary
                ) VALUES (
                    :claim_id,
                    :evidence_batch_id,
                    'supports',
                    1
                )"
            );

            $createdCount = 0;

            foreach ($claims as $claimText) {
                $claimStatement->execute([
                    'claim_text' => $claimText,
                    'normalized_text' => $this->normalizeClaim($claimText),
                    'primary_topic_id' => $primaryTopicId,
                    'created_by_admin_user_id' => $adminUserId,
                    'updated_by_admin_user_id' => $adminUserId,
                ]);

                $claimId = (int) $this->pdo->lastInsertId();

                $evidenceStatement->execute([
                    'claim_id' => $claimId,
                    'evidence_batch_id' => $evidenceBatchId,
                ]);

                $this->inheritSourceRelationships(
                    $sourceId,
                    $claimId,
                    $primaryTopicId,
                    $fairCycleId
                );

                $createdCount++;
            }

            $this->pdo->commit();

            return $createdCount;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function splitClaims(string $rawClaims): array
    {
        $rawClaims = trim($rawClaims);

        if ($rawClaims === '') {
            return [];
        }

        $parts = preg_split('/\R\s*\R+/', $rawClaims) ?: [];

        $claims = [];
        $seen = [];

        foreach ($parts as $part) {
            $claim = preg_replace('/\s+/u', ' ', trim($part));

            if ($claim === null || $claim === '') {
                continue;
            }

            $normalized = $this->normalizeClaim($claim);

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $claims[] = $claim;
        }

        return $claims;
    }

    private function normalizeClaim(string $claim): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($claim)) ?? trim($claim);

        if (function_exists('mb_strtolower')) {
            $normalized = mb_strtolower($normalized, 'UTF-8');
            return mb_substr($normalized, 0, 500, 'UTF-8');
        }

        return substr(strtolower($normalized), 0, 500);
    }

    private function findLatestSourceVersionId(int $sourceId): ?int
    {
        $statement = $this->pdo->prepare(
            "SELECT id
             FROM source_versions
             WHERE source_id = :source_id
             ORDER BY id DESC
             LIMIT 1"
        );

        $statement->execute(['source_id' => $sourceId]);

        $versionId = $statement->fetchColumn();

        return $versionId !== false ? (int) $versionId : null;
    }

    private function findSourcePrimaryTopicId(int $sourceId): ?int
    {
        $statement = $this->pdo->prepare(
            "SELECT primary_topic_id
             FROM sources
             WHERE id = :source_id"
        );

        $statement->execute(['source_id' => $sourceId]);

        $primaryTopicId = $statement->fetchColumn();

        if ($primaryTopicId !== false && $primaryTopicId !== null) {
            return (int) $primaryTopicId;
        }

        $statement = $this->pdo->prepare(
            "SELECT topic_id
             FROM source_topics
             WHERE source_id = :source_id
             ORDER BY topic_id
             LIMIT 1"
        );

        $statement->execute(['source_id' => $sourceId]);

        $topicId = $statement->fetchColumn();

        return $topicId !== false ? (int) $topicId : null;
    }

    private function inheritSourceRelationships(
        int $sourceId,
        int $claimId,
        ?int $primaryTopicId,
        ?int $fairCycleId
    ): void {
        $relationshipQueries = [
            "INSERT IGNORE INTO claim_topics (claim_id, topic_id)
             SELECT :claim_id, topic_id
             FROM source_topics
             WHERE source_id = :source_id",

            "INSERT IGNORE INTO claim_jurisdictions (claim_id, jurisdiction_id)
             SELECT :claim_id, jurisdiction_id
             FROM source_jurisdictions
             WHERE source_id = :source_id",

            "INSERT IGNORE INTO claim_projects (claim_id, project_id)
             SELECT :claim_id, project_id
             FROM source_projects
             WHERE source_id = :source_id",

            "INSERT IGNORE INTO claim_age_groups (claim_id, age_group_id)
             SELECT :claim_id, age_group_id
             FROM source_age_groups
             WHERE source_id = :source_id",
        ];

        foreach ($relationshipQueries as $sql) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'claim_id' => $claimId,
                'source_id' => $sourceId,
            ]);
        }

        if ($primaryTopicId !== null) {
            $statement = $this->pdo->prepare(
                "INSERT IGNORE INTO claim_topics (claim_id, topic_id)
                 VALUES (:claim_id, :topic_id)"
            );

            $statement->execute([
                'claim_id' => $claimId,
                'topic_id' => $primaryTopicId,
            ]);
        }

        if ($fairCycleId !== null) {
            $statement = $this->pdo->prepare(
                "INSERT IGNORE INTO claim_fair_cycles (
                    claim_id,
                    fair_cycle_id,
                    applicability_status
                ) VALUES (
                    :claim_id,
                    :fair_cycle_id,
                    'current'
                )"
            );

            $statement->execute([
                'claim_id' => $claimId,
                'fair_cycle_id' => $fairCycleId,
            ]);
        }
    }
}