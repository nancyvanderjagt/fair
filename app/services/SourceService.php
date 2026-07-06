<?php

declare(strict_types=1);

final class SourceService
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function create(
        array $input,
        int $adminUserId
    ): int {
        $data = $this->normalize($input);
        $this->validate($data);

        $this->pdo->beginTransaction();

        try {
            $organizationId = $this->getOrCreateOrganization(
                $data['organization'],
                $data['organization_type']
            );

            $statement = $this->pdo->prepare(
                'INSERT INTO sources (
                    organization_id,
                    title,
                    url,
                    source_type,
                    publication_version,
                    date_checked,
                    status,
                    visibility,
                    public_summary,
                    what_establishes,
                    important_language,
                    related_site_pages,
                    saved_copy_path,
                    internal_notes,
                    created_by,
                    updated_by,
                    archived_at
                ) VALUES (
                    :organization_id,
                    :title,
                    :url,
                    :source_type,
                    :publication_version,
                    :date_checked,
                    :status,
                    :visibility,
                    :public_summary,
                    :what_establishes,
                    :important_language,
                    :related_site_pages,
                    :saved_copy_path,
                    :internal_notes,
                    :created_by,
                    :updated_by,
                    :archived_at
                )'
            );

            $statement->execute([
                'organization_id' => $organizationId,
                'title' => $data['title'],
                'url' => $data['url'],
                'source_type' => $data['source_type'],
                'publication_version' =>
                    $this->nullable($data['publication_version']),
                'date_checked' =>
                    $this->nullable($data['date_checked']),
                'status' => $data['status'],
                'visibility' => $data['visibility'],
                'public_summary' =>
                    $this->nullable($data['public_summary']),
                'what_establishes' =>
                    $this->nullable($data['what_establishes']),
                'important_language' =>
                    $this->nullable($data['important_language']),
                'related_site_pages' =>
                    $this->nullable($data['related_site_pages']),
                'saved_copy_path' =>
                    $this->nullable($data['saved_copy_path']),
                'internal_notes' =>
                    $this->nullable($data['internal_notes']),
                'created_by' => $adminUserId,
                'updated_by' => $adminUserId,
                'archived_at' =>
                    $data['status'] === 'archived'
                        ? date('Y-m-d H:i:s')
                        : null,
            ]);

            $sourceId = (int) $this->pdo->lastInsertId();

            $jurisdictionGroups = [
                'state' => $data['states'],
                'county' => $data['counties'],
                'fair' => $data['fairs'],
                'club' => $data['clubs'],
            ];

            foreach ($jurisdictionGroups as $type => $names) {
                foreach ($names as $name) {
                    $jurisdictionId =
                        $this->getOrCreateJurisdiction(
                            $name,
                            $type
                        );

                    $this->attach(
                        'source_jurisdictions',
                        'jurisdiction_id',
                        $sourceId,
                        $jurisdictionId
                    );
                }
            }

            foreach ($data['projects'] as $name) {
                $projectId = $this->getOrCreateProject($name);

                $this->attach(
                    'source_projects',
                    'project_id',
                    $sourceId,
                    $projectId
                );
            }

            foreach ($data['topics'] as $name) {
                $topicId = $this->getOrCreateTopic($name);

                $this->attach(
                    'source_topics',
                    'topic_id',
                    $sourceId,
                    $topicId
                );
            }

            foreach ($data['age_groups'] as $name) {
                $ageGroupId = $this->getOrCreateAgeGroup($name);

                $this->attach(
                    'source_age_groups',
                    'age_group_id',
                    $sourceId,
                    $ageGroupId
                );
            }

            $snapshot = [
                'source' => $data,
                'organization_id' => $organizationId,
            ];

            $versionStatement = $this->pdo->prepare(
                'INSERT INTO source_versions (
                    source_id,
                    version_number,
                    snapshot,
                    change_summary,
                    created_by
                ) VALUES (
                    :source_id,
                    1,
                    :snapshot,
                    :change_summary,
                    :created_by
                )'
            );

            $versionStatement->execute([
                'source_id' => $sourceId,
                'snapshot' => json_encode(
                    $snapshot,
                    JSON_THROW_ON_ERROR
                ),
                'change_summary' => 'Initial source record created.',
                'created_by' => $adminUserId,
            ]);

            $this->pdo->commit();

            return $sourceId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function normalize(array $input): array
    {
        return [
            'title' => trim((string) ($input['title'] ?? '')),
            'organization' =>
                trim((string) ($input['organization'] ?? '')),
            'organization_type' =>
                trim((string) ($input['organization_type'] ?? '')),
            'url' => trim((string) ($input['url'] ?? '')),
            'source_type' =>
                trim((string) ($input['source_type'] ?? '')),
            'publication_version' =>
                trim((string) ($input['publication_version'] ?? '')),
            'date_checked' =>
                trim((string) ($input['date_checked'] ?? '')),
            'status' =>
                trim((string) ($input['status'] ?? 'needs_review')),
            'visibility' =>
                trim((string) ($input['visibility'] ?? 'internal')),
            'public_summary' =>
                trim((string) ($input['public_summary'] ?? '')),
            'what_establishes' =>
                trim((string) ($input['what_establishes'] ?? '')),
            'important_language' =>
                trim((string) ($input['important_language'] ?? '')),
            'related_site_pages' =>
                trim((string) ($input['related_site_pages'] ?? '')),
            'saved_copy_path' =>
                trim((string) ($input['saved_copy_path'] ?? '')),
            'internal_notes' =>
                trim((string) ($input['internal_notes'] ?? '')),
            'states' =>
                $this->splitList((string) ($input['states'] ?? '')),
            'counties' =>
                $this->splitList((string) ($input['counties'] ?? '')),
            'fairs' =>
                $this->splitList((string) ($input['fairs'] ?? '')),
            'clubs' =>
                $this->splitList((string) ($input['clubs'] ?? '')),
            'projects' =>
                $this->splitList((string) ($input['projects'] ?? '')),
            'topics' =>
                $this->splitList((string) ($input['topics'] ?? '')),
            'age_groups' =>
                $this->splitList((string) ($input['age_groups'] ?? '')),
        ];
    }

    private function validate(array $data): void
    {
        $errors = [];

        if ($data['title'] === '') {
            $errors[] = 'Title is required.';
        }

        if ($data['organization'] === '') {
            $errors[] = 'Organization is required.';
        }

        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Enter a valid source URL.';
        }

        $allowedSourceTypes = [
            'web_page',
            'pdf',
            'form',
            'handbook',
            'workbook',
            'portal',
            'other',
        ];

        if (!in_array(
            $data['source_type'],
            $allowedSourceTypes,
            true
        )) {
            $errors[] = 'Select a valid source type.';
        }

        $allowedStatuses = [
            'current',
            'possible_update',
            'broken_link',
            'archived',
            'needs_review',
        ];

        if (!in_array($data['status'], $allowedStatuses, true)) {
            $errors[] = 'Select a valid source status.';
        }

        if (!in_array(
            $data['visibility'],
            ['internal', 'public'],
            true
        )) {
            $errors[] = 'Select a valid visibility setting.';
        }

        if (
            $data['date_checked'] !== ''
            && !$this->validDate($data['date_checked'])
        ) {
            $errors[] = 'Date checked must be a valid date.';
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(
                implode(' ', $errors)
            );
        }
    }

    private function validDate(string $date): bool
    {
        $value = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $date
        );

        return $value !== false
            && $value->format('Y-m-d') === $date;
    }

    private function getOrCreateOrganization(
        string $name,
        string $type
    ): int {
        $statement = $this->pdo->prepare(
            'SELECT id
             FROM organizations
             WHERE name = :name
             LIMIT 1'
        );

        $statement->execute(['name' => $name]);

        $existingId = $statement->fetchColumn();

        if ($existingId !== false) {
            return (int) $existingId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO organizations (
                name,
                organization_type
             ) VALUES (
                :name,
                :organization_type
             )'
        );

        $insert->execute([
            'name' => $name,
            'organization_type' => $this->nullable($type),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function getOrCreateJurisdiction(
        string $name,
        string $type
    ): int {
        $slug = $type . '-' . $this->slugify($name);

        $statement = $this->pdo->prepare(
            'SELECT id
             FROM jurisdictions
             WHERE slug = :slug
             LIMIT 1'
        );

        $statement->execute(['slug' => $slug]);

        $existingId = $statement->fetchColumn();

        if ($existingId !== false) {
            return (int) $existingId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO jurisdictions (
                name,
                slug,
                jurisdiction_type
             ) VALUES (
                :name,
                :slug,
                :jurisdiction_type
             )'
        );

        $insert->execute([
            'name' => $name,
            'slug' => $slug,
            'jurisdiction_type' => $type,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function getOrCreateProject(string $name): int
    {
        return $this->getOrCreateNamedRecord(
            'projects',
            $name
        );
    }

    private function getOrCreateTopic(string $name): int
    {
        return $this->getOrCreateNamedRecord(
            'topics',
            $name
        );
    }

    private function getOrCreateAgeGroup(string $name): int
    {
        return $this->getOrCreateNamedRecord(
            'age_groups',
            $name
        );
    }

    private function getOrCreateNamedRecord(
        string $table,
        string $name
    ): int {
        $allowedTables = [
            'projects',
            'topics',
            'age_groups',
        ];

        if (!in_array($table, $allowedTables, true)) {
            throw new InvalidArgumentException(
                'Invalid lookup table.'
            );
        }

        $slug = $this->slugify($name);

        $statement = $this->pdo->prepare(
            "SELECT id
             FROM {$table}
             WHERE slug = :slug
             LIMIT 1"
        );

        $statement->execute(['slug' => $slug]);

        $existingId = $statement->fetchColumn();

        if ($existingId !== false) {
            return (int) $existingId;
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO {$table} (
                name,
                slug
             ) VALUES (
                :name,
                :slug
             )"
        );

        $insert->execute([
            'name' => $name,
            'slug' => $slug,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function attach(
        string $table,
        string $foreignColumn,
        int $sourceId,
        int $foreignId
    ): void {
        $allowed = [
            'source_jurisdictions' => 'jurisdiction_id',
            'source_projects' => 'project_id',
            'source_topics' => 'topic_id',
            'source_age_groups' => 'age_group_id',
        ];

        if (
            !isset($allowed[$table])
            || $allowed[$table] !== $foreignColumn
        ) {
            throw new InvalidArgumentException(
                'Invalid source relationship.'
            );
        }

        $statement = $this->pdo->prepare(
            "INSERT IGNORE INTO {$table} (
                source_id,
                {$foreignColumn}
             ) VALUES (
                :source_id,
                :foreign_id
             )"
        );

        $statement->execute([
            'source_id' => $sourceId,
            'foreign_id' => $foreignId,
        ]);
    }

    private function splitList(string $value): array
    {
        $items = preg_split('/[\r\n,]+/', $value) ?: [];

        $items = array_map('trim', $items);
        $items = array_filter(
            $items,
            static fn (string $item): bool => $item !== ''
        );

        return array_values(array_unique($items));
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            return substr(hash('sha256', $value), 0, 16);
        }

        return $slug;
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}