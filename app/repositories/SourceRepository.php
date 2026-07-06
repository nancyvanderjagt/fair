<?php

declare(strict_types=1);

final class SourceRepository
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function search(array $filters = []): array
    {
        $where = [];
        $parameters = [];

        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $visibility = trim(
            (string) ($filters['visibility'] ?? '')
        );

        if ($search !== '') {
            $where[] = '(
                s.title LIKE :search_title
                OR s.url LIKE :search_url
                OR o.name LIKE :search_organization
                OR s.public_summary LIKE :search_summary
                OR s.what_establishes LIKE :search_establishes
            )';

            $searchValue = '%' . $search . '%';

            $parameters['search_title'] = $searchValue;
            $parameters['search_url'] = $searchValue;
            $parameters['search_organization'] = $searchValue;
            $parameters['search_summary'] = $searchValue;
            $parameters['search_establishes'] = $searchValue;
        }

        $allowedStatuses = [
            'current',
            'possible_update',
            'broken_link',
            'archived',
            'needs_review',
        ];

        if (in_array($status, $allowedStatuses, true)) {
            $where[] = 's.status = :status';
            $parameters['status'] = $status;
        }

        $allowedVisibility = [
            'internal',
            'public',
        ];

        if (in_array(
            $visibility,
            $allowedVisibility,
            true
        )) {
            $where[] = 's.visibility = :visibility';
            $parameters['visibility'] = $visibility;
        }

        $whereSql = $where === []
            ? ''
            : 'WHERE ' . implode(' AND ', $where);

        $sql = <<<SQL
            SELECT
                s.id,
                s.title,
                s.url,
                s.source_type,
                s.publication_version,
                s.date_checked,
                s.status,
                s.visibility,
                s.public_summary,
                s.what_establishes,
                s.important_language,
                s.related_site_pages,
                s.saved_copy_path,
                s.internal_notes,
                s.created_at,
                s.updated_at,
                s.archived_at,
                o.name AS organization,

                (
                    SELECT GROUP_CONCAT(
                        DISTINCT CONCAT(
                            j.jurisdiction_type,
                            ':',
                            j.name
                        )
                        ORDER BY
                            j.jurisdiction_type,
                            j.name
                        SEPARATOR '||'
                    )
                    FROM source_jurisdictions sj
                    INNER JOIN jurisdictions j
                        ON j.id = sj.jurisdiction_id
                    WHERE sj.source_id = s.id
                ) AS jurisdictions,

                (
                    SELECT GROUP_CONCAT(
                        DISTINCT p.name
                        ORDER BY p.name
                        SEPARATOR '||'
                    )
                    FROM source_projects sp
                    INNER JOIN projects p
                        ON p.id = sp.project_id
                    WHERE sp.source_id = s.id
                ) AS projects,

                (
                    SELECT GROUP_CONCAT(
                        DISTINCT t.name
                        ORDER BY t.name
                        SEPARATOR '||'
                    )
                    FROM source_topics st
                    INNER JOIN topics t
                        ON t.id = st.topic_id
                    WHERE st.source_id = s.id
                ) AS topics,

                (
                    SELECT GROUP_CONCAT(
                        DISTINCT ag.name
                        ORDER BY ag.name
                        SEPARATOR '||'
                    )
                    FROM source_age_groups sag
                    INNER JOIN age_groups ag
                        ON ag.id = sag.age_group_id
                    WHERE sag.source_id = s.id
                ) AS age_groups,

                (
                    SELECT GROUP_CONCAT(
                        DISTINCT tag.name
                        ORDER BY tag.name
                        SEPARATOR '||'
                    )
                    FROM source_tags source_tag
                    INNER JOIN tags tag
                        ON tag.id = source_tag.tag_id
                    WHERE source_tag.source_id = s.id
                ) AS tags

            FROM sources s

            LEFT JOIN organizations o
                ON o.id = s.organization_id

            {$whereSql}

            ORDER BY
                CASE s.status
                    WHEN 'possible_update' THEN 1
                    WHEN 'needs_review' THEN 2
                    WHEN 'broken_link' THEN 3
                    WHEN 'current' THEN 4
                    WHEN 'archived' THEN 5
                    ELSE 6
                END,
                s.updated_at DESC,
                s.title ASC

            LIMIT 500
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }
}