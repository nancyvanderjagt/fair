<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../../app/auth/require-login.php';

require_once __DIR__
    . '/../../../app/auth/csrf.php';

require_once __DIR__
    . '/../../../app/services/SourceService.php';

if (
    !isset($_SESSION['source_fetch_csrf_token'])
    || !is_string($_SESSION['source_fetch_csrf_token'])
) {
    $_SESSION['source_fetch_csrf_token'] =
        bin2hex(random_bytes(32));
}

$adminPageTitle = 'Add Source';

function form_value(
    array $form,
    string $key,
    string $default = ''
): string {
    return htmlspecialchars(
        (string) ($form[$key] ?? $default),
        ENT_QUOTES,
        'UTF-8'
    );
}

$form = [
    'title' => '',
    'organization' => '',
    'organization_type' => '',
    'url' => '',
    'source_type' => 'web_page',
    'publication_version' => '',
    'date_checked' => date('Y-m-d'),
    'status' => 'needs_review',
    'visibility' => 'internal',
    'public_summary' => '',
    'what_establishes' => '',
    'important_language' => '',
    'related_site_pages' => '',
    'saved_copy_path' => '',
    'internal_notes' => '',
    'states' => '',
    'counties' => '',
    'fairs' => '',
    'clubs' => '',
    'projects' => '',
    'topics' => '',
    'age_groups' => '',
    'tags' => '',
];



$error = '';
$createdSourceId = isset($_GET['created'])
    ? (int) $_GET['created']
    : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, $_POST);

    verify_csrf('create-source');

    try {
        $service = new SourceService(db());

        $sourceId = $service->create(
            $_POST,
            (int) $currentAdmin['id']
        );

        header(
            'Location: /admin/sources/create.php?created='
            . $sourceId
        );

        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log(
            'Create source error: '
            . $exception->getMessage()
        );

        $error =
            'The source could not be saved. Please try again.';
    }
}

require_once __DIR__
    . '/../../../app/includes/admin-header.php';

?>

<section class="page-hero">
    <div class="container narrow">

        <p class="eyebrow">Source library</p>

        <h1>Add a source</h1>

        <p>
            Save the source once, then assign it to every state,
            county, fair, club, project, topic, and age group
            where it applies.
        </p>

    </div>
</section>


<section class="section">
    <div class="container narrow">

        <?php if ($createdSourceId > 0): ?>
            <div class="callout">
                <strong>Source saved successfully.</strong>

                The new source ID is
                <?= $createdSourceId ?>.
            </div>
        <?php endif; ?>


        <?php if ($error !== ''): ?>
            <div class="warning">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>


        <form method="post" action="/admin/sources/create.php">

            <?= csrf_input('create-source') ?>


            <div class="card">
                <p class="eyebrow">Basic information</p>

                <h2>Source details</h2>

                <p>
                    <label for="url">
                        <strong>URL</strong>
                    </label>

                    <input
                        id="url"
                        name="url"
                        type="url"
                        value="<?= form_value($form, 'url') ?>"
                        required
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <p>
                    <button
                        id="fetch-source-details"
                        class="button secondary"
                        type="button"
                        data-csrf-token="<?= htmlspecialchars(
                            $_SESSION['source_fetch_csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                        Fetch details
                    </button>
                </p>

                <p
                    id="source-fetch-status"
                    class="small-note"
                    aria-live="polite"
                >
                    Paste a URL, then fetch the details the site makes available.
                </p>
                
                <p>
                    <label for="title">
                        <strong>Title</strong>
                    </label>

                    <input
                        id="title"
                        name="title"
                        type="text"
                        value="<?= form_value($form, 'title') ?>"
                        required
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <p>
                    <label for="organization">
                        <strong>Organization</strong>
                    </label>

                    <input
                        id="organization"
                        name="organization"
                        type="text"
                        value="<?= form_value(
                            $form,
                            'organization'
                        ) ?>"
                        required
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <p>
                    <label for="organization_type">
                        <strong>Organization type</strong>
                    </label>

                    <select
                        id="organization_type"
                        name="organization_type"
                        style="width: 100%; padding: 12px;"
                    >
                        <?php
                        $organizationTypes = [
                            '' => 'Choose a type',
                            'extension' => 'Extension',
                            'county_4h' => 'County 4-H program',
                            'fair' => 'Fair organization',
                            'state_4h' => 'State 4-H program',
                            'software_provider' => 'Software provider',
                            'government' => 'Government agency',
                            'other' => 'Other',
                        ];
                        ?>

                        <?php foreach (
                            $organizationTypes as $value => $label
                        ): ?>
                            <option
                                value="<?= $value ?>"
                                <?= ($form['organization_type'] ?? '')
                                    === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $label,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                

                <p>
                    <label for="source_type">
                        <strong>Source type</strong>
                    </label>

                    <select
                        id="source_type"
                        name="source_type"
                        required
                        style="width: 100%; padding: 12px;"
                    >
                        <?php
                        $sourceTypes = [
                            'web_page' => 'Web page',
                            'pdf' => 'PDF',
                            'form' => 'Form',
                            'handbook' => 'Handbook',
                            'workbook' => 'Workbook',
                            'portal' => 'Online portal',
                            'other' => 'Other',
                        ];
                        ?>

                        <?php foreach (
                            $sourceTypes as $value => $label
                        ): ?>
                            <option
                                value="<?= $value ?>"
                                <?= ($form['source_type'] ?? '')
                                    === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="publication_version">
                        <strong>Publication year or version</strong>
                    </label>

                    <input
                        id="publication_version"
                        name="publication_version"
                        type="text"
                        value="<?= form_value(
                            $form,
                            'publication_version'
                        ) ?>"
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <p>
                    <label for="date_checked">
                        <strong>Date checked</strong>
                    </label>

                    <input
                        id="date_checked"
                        name="date_checked"
                        type="date"
                        value="<?= form_value(
                            $form,
                            'date_checked'
                        ) ?>"
                        style="width: 100%; padding: 12px;"
                    >
                </p>
            </div>


            <div class="card" style="margin-top: 24px;">
                <p class="eyebrow">Structured relationships</p>

                <h2>Where does this source apply?</h2>

                <p>
                    Enter multiple values separated by commas
                    or place each value on its own line.
                </p>

                <?php
                $relationshipFields = [
                    'states' => 'States',
                    'counties' => 'Counties',
                    'fairs' => 'Fairs',
                    'clubs' => 'Clubs',
                    'projects' => 'Projects',
                    'topics' => 'Topics',
                    'age_groups' => 'Age groups',
                    'tags' => 'Tags',
                ];
                ?>

                <?php foreach (
                    $relationshipFields as $field => $label
                ): ?>
                    <p>
                        <label for="<?= $field ?>">
                            <strong><?= $label ?></strong>
                        </label>

                        <textarea
                            id="<?= $field ?>"
                            name="<?= $field ?>"
                            rows="2"
                            style="width: 100%; padding: 12px;"
                        ><?= form_value($form, $field) ?></textarea>
                    </p>
                <?php endforeach; ?>
            </div>


            <div class="card" style="margin-top: 24px;">
                <p class="eyebrow">Review and publication</p>

                <h2>Source status</h2>

                <p>
                    <label for="status">
                        <strong>Current status</strong>
                    </label>

                    <select
                        id="status"
                        name="status"
                        style="width: 100%; padding: 12px;"
                    >
                        <?php
                        $statuses = [
                            'needs_review' => 'Needs review',
                            'current' => 'Current',
                            'possible_update' => 'Possible update',
                            'broken_link' => 'Broken link',
                            'archived' => 'Archived',
                        ];
                        ?>

                        <?php foreach (
                            $statuses as $value => $label
                        ): ?>
                            <option
                                value="<?= $value ?>"
                                <?= ($form['status'] ?? '')
                                    === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $label ?>
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
                        <option
                            value="internal"
                            <?= ($form['visibility'] ?? '')
                                === 'internal'
                                ? 'selected'
                                : '' ?>
                        >
                            Internal only
                        </option>

                        <option
                            value="public"
                            <?= ($form['visibility'] ?? '')
                                === 'public'
                                ? 'selected'
                                : '' ?>
                        >
                            Public
                        </option>
                    </select>
                </p>

                <?php
                $longFields = [
                    'public_summary' =>
                        'Public summary',
                    'what_establishes' =>
                        'What this source establishes',
                    'important_language' =>
                        'Important language to verify',
                    'related_site_pages' =>
                        'Related site pages',
                    'internal_notes' =>
                        'Internal notes',
                ];
                ?>

                <?php foreach (
                    $longFields as $field => $label
                ): ?>
                    <p>
                        <label for="<?= $field ?>">
                            <strong><?= $label ?></strong>
                        </label>

                        <textarea
                            id="<?= $field ?>"
                            name="<?= $field ?>"
                            rows="4"
                            style="width: 100%; padding: 12px;"
                        ><?= form_value($form, $field) ?></textarea>
                    </p>
                <?php endforeach; ?>

                <p>
                    <label for="saved_copy_path">
                        <strong>Saved copy or prior version</strong>
                    </label>

                    <input
                        id="saved_copy_path"
                        name="saved_copy_path"
                        type="text"
                        value="<?= form_value(
                            $form,
                            'saved_copy_path'
                        ) ?>"
                        style="width: 100%; padding: 12px;"
                    >
                </p>
            </div>


            <p style="margin-top: 24px;">
                <button
                    class="button primary"
                    type="submit"
                >
                    Save source
                </button>

                <a
                    class="button secondary"
                    href="/admin/index.php"
                >
                    Cancel
                </a>
            </p>

        </form>

    </div>
</section>

<script src="/assets/js/source-fetch.js"></script>

<?php

require_once __DIR__
    . '/../../../app/includes/admin-footer.php';