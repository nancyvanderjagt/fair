<?php

declare(strict_types=1);

final class ResearchQuestionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getQuestionsForSource(int $sourceId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT
                rq.id,
                rq.question_text,
                rq.status,
                rq.priority,
                rq.created_at,
                t.name AS category_name,
                fc.label AS fair_cycle_label,
                f.name AS fair_name
             FROM research_questions rq
             INNER JOIN research_question_sources rqs
                ON rqs.research_question_id = rq.id
             LEFT JOIN topics t
                ON t.id = rq.primary_topic_id
             LEFT JOIN fair_cycles fc
                ON fc.id = rq.fair_cycle_id
             LEFT JOIN fairs f
                ON f.id = fc.fair_id
             WHERE rqs.source_id = :source_id
             ORDER BY
                CASE rq.status
                    WHEN 'open' THEN 1
                    WHEN 'investigating' THEN 2
                    WHEN 'waiting_for_response' THEN 3
                    WHEN 'answered' THEN 4
                    ELSE 5
                END,
                rq.created_at DESC,
                rq.id DESC"
        );

        $statement->execute(['source_id' => $sourceId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createQuestionsFromSource(
        int $sourceId,
        string $rawQuestions,
        ?int $fairCycleId = null,
        string $priority = 'normal',
        ?int $adminUserId = null
    ): int {
        $questions = $this->splitQuestions($rawQuestions);

        if ($questions === []) {
            throw new InvalidArgumentException(
                'Enter at least one question. Separate questions with a blank line.'
            );
        }

        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        if (!$this->sourceExists($sourceId)) {
            throw new RuntimeException('The selected source could not be found.');
        }

        $sourceVersionId = $this->findLatestSourceVersionId($sourceId);
        $primaryTopicId = $this->findSourcePrimaryTopicId($sourceId);

        $this->pdo->beginTransaction();

        try {
            $questionStatement = $this->pdo->prepare(
                "INSERT INTO research_questions (
                    question_text,
                    normalized_text,
                    primary_topic_id,
                    fair_cycle_id,
                    status,
                    priority,
                    created_by_admin_user_id,
                    updated_by_admin_user_id
                ) VALUES (
                    :question_text,
                    :normalized_text,
                    :primary_topic_id,
                    :fair_cycle_id,
                    'open',
                    :priority,
                    :created_by_admin_user_id,
                    :updated_by_admin_user_id
                )"
            );

            $sourceStatement = $this->pdo->prepare(
                "INSERT INTO research_question_sources (
                    research_question_id,
                    source_id,
                    source_version_id,
                    relationship_type
                ) VALUES (
                    :research_question_id,
                    :source_id,
                    :source_version_id,
                    'raised_by'
                )"
            );

            $createdCount = 0;

            foreach ($questions as $questionText) {
                $questionStatement->execute([
                    'question_text' => $questionText,
                    'normalized_text' => $this->normalizeText($questionText),
                    'primary_topic_id' => $primaryTopicId,
                    'fair_cycle_id' => $fairCycleId,
                    'priority' => $priority,
                    'created_by_admin_user_id' => $adminUserId,
                    'updated_by_admin_user_id' => $adminUserId,
                ]);

                $questionId = (int) $this->pdo->lastInsertId();

                $sourceStatement->execute([
                    'research_question_id' => $questionId,
                    'source_id' => $sourceId,
                    'source_version_id' => $sourceVersionId,
                ]);

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

    private function splitQuestions(string $rawQuestions): array
    {
        $rawQuestions = trim($rawQuestions);

        if ($rawQuestions === '') {
            return [];
        }

        $parts = preg_split('/\R\s*\R+/', $rawQuestions) ?: [];

        $questions = [];
        $seen = [];

        foreach ($parts as $part) {
            $question = preg_replace('/\s+/u', ' ', trim($part));

            if ($question === null || $question === '') {
                continue;
            }

            $normalized = $this->normalizeText($question);

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $questions[] = $question;
        }

        return $questions;
    }

    private function normalizeText(string $text): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        if (function_exists('mb_strtolower')) {
            return mb_substr(
                mb_strtolower($normalized, 'UTF-8'),
                0,
                500,
                'UTF-8'
            );
        }

        return substr(strtolower($normalized), 0, 500);
    }

    private function sourceExists(int $sourceId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM sources
             WHERE id = :source_id"
        );

        $statement->execute(['source_id' => $sourceId]);

        return (int) $statement->fetchColumn() > 0;
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
}