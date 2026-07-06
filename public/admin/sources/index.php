<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../../app/auth/require-login.php';

require_once __DIR__
    . '/../../../app/repositories/SourceRepository.php';

$adminPageTitle = 'Sources';

function source_escape(string|int|null $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function source_list(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }

    return array_values(
        array_filter(
            array_map(
                'trim',
                explode('||', $value)
            )
        )
    );
}

function source_status_label(string $status): string
{
    return match ($status) {
        'current' => 'Current',
        'possible_update' => 'Possible update',
        'broken_link' => 'Broken link',
        'archived' => 'Archived',
        default => 'Needs review',
    };
}

function source_status_class(string $status): string
{
    return match ($status) {
        'current' => 'official',
        'possible_update',
        'needs_review' => 'confirm',
        default => 'draft',
    };
}

function source_type_label(string $type): string
{
    return match ($type) {
        'web_page' => 'Web page',
        'pdf' => 'PDF',
        'form' => 'Form',
        'handbook' => 'Handbook',
        'workbook' => 'Workbook',
        'portal' => 'Online portal',
        default => 'Other',
    };
}

function jurisdiction_label(string $value): string
{
    $parts = explode(':', $value, 2);

    if (count($parts) !== 2) {
        return $value;
    }

    [$type, $name] = $parts;

    $typeLabel = match ($type) {
        'state' => 'State',
        'county' => 'County',
        'fair' => 'Fair',
        'club' => 'Club',
        default => ucfirst($type),
    };

    return $typeLabel . ': ' . $name;
}

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'visibility' =>
        trim((string) ($_GET['visibility'] ?? '')),
];

$sources = [];
$error = '';

try {
    $repository = new SourceRepository(db());
    $sources = $repository->search($filters);
} catch (Throwable $exception) {
    error_log(
        'Source list error: '
        . $exception->getMessage()
    );

    $error =
        'The sources could not be loaded. Please try again.';
}

require_once __DIR__
    . '/../../../app/includes/admin-header.php';

?>

<section class="page-hero">
    <div class="container">

        <p class="eyebrow">Source library</p>

        <h1>Sources</h1>

        <p>
            Review official pages, forms, handbooks, workbooks,
            portals, and other information saved in the Fair database.
        </p>

        <div class="button-row">
            <a
                class="button primary"
                href="/admin/sources/create.php"
            >
                Add source
            </a>

            <a
                class="button secondary"
                href="/resources.php"
                target="_blank"
                rel="noopener"
            >
                View public resources
            </a>
        </div>

    </div>
</section>


<section class="section">
    <div class="container">

        <?php if ($error !== ''): ?>
            <div class="warning">
                <?= source_escape($error) ?>
            </div>
        <?php endif; ?>


        <div class="callout">
            <strong>
                <?= count($sources) ?>
                source<?= count($sources) === 1 ? '' : 's' ?>
                found
            </strong>

            <p>
                Internal sources remain visible here but do not
                automatically appear on the public website.
            </p>
        </div>


        <form
            method="get"
            action="/admin/sources/index.php"
            class="card"
            style="margin-bottom: 30px;"
        >
            <p class="eyebrow">Find and filter</p>

            <div
                style="
                    display: grid;
                    grid-template-columns:
                        minmax(220px, 2fr)
                        minmax(170px, 1fr)
                        minmax(170px, 1fr);
                    gap: 16px;
                "
            >
                <p>
                    <label for="search">
                        <strong>Search</strong>
                    </label>

                    <input
                        id="search"
                        name="search"
                        type="search"
                        value="<?= source_escape(
                            $filters['search']
                        ) ?>"
                        placeholder="Title, organization, URL, or summary"
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <p>
                    <label for="status">
                        <strong>Status</strong>
                    </label>

                    <select
                        id="status"
                        name="status"
                        style="width: 100%; padding: 12px;"
                    >
                        <option value="">All statuses</option>

                        <?php
                        $statusOptions = [
                            'needs_review' => 'Needs review',
                            'current' => 'Current',
                            'possible_update' =>
                                'Possible update',
                            'broken_link' => 'Broken link',
                            'archived' => 'Archived',
                        ];
                        ?>

                        <?php foreach (
                            $statusOptions as $value => $label
                        ): ?>
                            <option
                                value="<?= source_escape($value) ?>"
                                <?= $filters['status'] === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= source_escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="visibility">
                        <strong>Visibility</strong>
                    </label>

                    <select
                        id="visibility"
                        name="visibility"
                        style="width: 100%; padding: 12px;"
                    >
                        <option value="">All visibility</option>

                        <option
                            value="internal"
                            <?= $filters['visibility'] === 'internal'
                                ? 'selected'
                                : '' ?>
                        >
                            Internal only
                        </option>

                        <option
                            value="public"
                            <?= $filters['visibility'] === 'public'
                                ? 'selected'
                                : '' ?>
                        >
                            Public
                        </option>
                    </select>
                </p>
            </div>

            <div class="button-row">
                <button
                    class="button primary"
                    type="submit"
                >
                    Apply filters
                </button>

                <a
                    class="button secondary"
                    href="/admin/sources/index.php"
                >
                    Clear filters
                </a>
            </div>
        </form>


        <?php if ($sources === [] && $error === ''): ?>

            <div class="callout">
                <h2>No sources found</h2>

                <p>
                    Try clearing the filters or add the first
                    source to the database.
                </p>

                <a
                    class="button primary"
                    href="/admin/sources/create.php"
                >
                    Add source
                </a>
            </div>

        <?php else: ?>

            <div class="card-grid">

                <?php foreach ($sources as $source): ?>

                    <?php
                    $jurisdictions = source_list(
                        $source['jurisdictions'] ?? null
                    );

                    $projects = source_list(
                        $source['projects'] ?? null
                    );

                    $topics = source_list(
                        $source['topics'] ?? null
                    );

                    $ageGroups = source_list(
                        $source['age_groups'] ?? null
                    );

                    $tags = source_list(
                        $source['tags'] ?? null
                    );
                    ?>

                    <article class="card">

                        <p class="eyebrow">
                            <?= source_escape(
                                $source['organization']
                                ?: 'Organization not listed'
                            ) ?>
                        </p>

                        <h2>
                            <a href="/admin/sources/view.php?id=<?= (int) $source['id'] ?>">
                                <?= source_escape($source['title']) ?>
                            </a>
                        </h2>

                        <p>
                            <span
                                class="status <?= source_escape(
                                    source_status_class(
                                        $source['status']
                                    )
                                ) ?>"
                            >
                                <?= source_escape(
                                    source_status_label(
                                        $source['status']
                                    )
                                ) ?>
                            </span>

                            <span class="status draft">
                                <?= $source['visibility'] === 'public'
                                    ? 'Public'
                                    : 'Internal' ?>
                            </span>
                        </p>

                        <?php if (
                            trim(
                                (string) $source['public_summary']
                            ) !== ''
                        ): ?>
                            <p>
                                <?= nl2br(
                                    source_escape(
                                        $source['public_summary']
                                    )
                                ) ?>
                            </p>
                        <?php elseif (
                            trim(
                                (string) $source['what_establishes']
                            ) !== ''
                        ): ?>
                            <p>
                                <?= nl2br(
                                    source_escape(
                                        $source['what_establishes']
                                    )
                                ) ?>
                            </p>
                        <?php endif; ?>

                        <p class="small-note">
                            <strong>Type:</strong>
                            <?= source_escape(
                                source_type_label(
                                    $source['source_type']
                                )
                            ) ?>

                            <br>

                            <strong>Checked:</strong>
                            <?= $source['date_checked']
                                ? source_escape(
                                    date(
                                        'F j, Y',
                                        strtotime(
                                            $source['date_checked']
                                        )
                                    )
                                )
                                : 'Not recorded' ?>

                            <?php if (
                                trim(
                                    (string)
                                    $source['publication_version']
                                ) !== ''
                            ): ?>
                                <br>

                                <strong>Version:</strong>
                                <?= source_escape(
                                    $source[
                                        'publication_version'
                                    ]
                                ) ?>
                            <?php endif; ?>
                        </p>


                        <?php if ($jurisdictions !== []): ?>
                            <p>
                                <strong>Applies to</strong>
                            </p>

                            <p>
                                <?php foreach (
                                    $jurisdictions as $item
                                ): ?>
                                    <span class="status draft">
                                        <?= source_escape(
                                            jurisdiction_label($item)
                                        ) ?>
                                    </span>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>


                        <?php if ($projects !== []): ?>
                            <p>
                                <strong>Projects:</strong>
                                <?= source_escape(
                                    implode(', ', $projects)
                                ) ?>
                            </p>
                        <?php endif; ?>


                        <?php if ($topics !== []): ?>
                            <p>
                                <strong>Topics:</strong>
                                <?= source_escape(
                                    implode(', ', $topics)
                                ) ?>
                            </p>
                        <?php endif; ?>


                        <?php if ($ageGroups !== []): ?>
                            <p>
                                <strong>Age groups:</strong>
                                <?= source_escape(
                                    implode(', ', $ageGroups)
                                ) ?>
                            </p>
                        <?php endif; ?>


                        <?php if ($tags !== []): ?>
                            <p>
                                <?php foreach ($tags as $tag): ?>
                                    <span class="status draft">
                                        #<?= source_escape($tag) ?>
                                    </span>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>


                        <div class="button-row">

                            <a
                                class="button secondary"
                                href="<?= source_escape(
                                    $source['url']
                                ) ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                Open source
                            </a>

                            <span class="small-note">
                                Source #<?= (int) $source['id'] ?>
                            </span>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>
</section>

<?php

require_once __DIR__
    . '/../../../app/includes/admin-footer.php';