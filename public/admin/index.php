<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/require-login.php';

$adminPageTitle = 'Dashboard';

try {
    $sourceStats = db()
        ->query(
            'SELECT
                COUNT(*) AS total_sources,
                COALESCE(
                    SUM(status = "needs_review"),
                    0
                ) AS needs_review,
                COALESCE(
                    SUM(visibility = "public"),
                    0
                ) AS public_sources,
                COALESCE(
                    SUM(archived_at IS NOT NULL),
                    0
                ) AS archived_sources
             FROM sources'
        )
        ->fetch();

    if (!is_array($sourceStats)) {
        $sourceStats = [];
    }
} catch (Throwable $exception) {
    error_log(
        'Admin dashboard statistics error: '
        . $exception->getMessage()
    );

    $sourceStats = [];
}

$sourceStats = array_merge(
    [
        'total_sources' => 0,
        'needs_review' => 0,
        'public_sources' => 0,
        'archived_sources' => 0,
    ],
    $sourceStats
);

require_once __DIR__
    . '/../../app/includes/admin-header.php';

?>

<section class="page-hero">
    <div class="container narrow">

        <p class="eyebrow">Private administration</p>

        <h1>Welcome, <?= htmlspecialchars(
            $currentAdmin['display_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?></h1>

        <p>
            Manage official sources, review changing information,
            and control what appears on the public Fair website.
        </p>

    </div>
</section>


<section class="section">
    <div class="container">

        <div class="card-grid">

            <article class="card">
                <p class="eyebrow">Sources</p>

                <h2>
                    <?= (int) $sourceStats['total_sources'] ?>
                </h2>

                <p>
                    Total sources currently stored in the database.
                </p>

                <a href="/admin/sources/">
                    View all sources →
                </a>
            </article>


            <article class="card">
                <p class="eyebrow">Review queue</p>

                <h2>
                    <?= (int) $sourceStats['needs_review'] ?>
                </h2>

                <p>
                    Sources marked as needing verification or review.
                </p>

                <a href="/admin/sources/?status=needs_review">
                    Review sources →
                </a>
            </article>


            <article class="card">
                <p class="eyebrow">Public library</p>

                <h2>
                    <?= (int) $sourceStats['public_sources'] ?>
                </h2>

                <p>
                    Sources approved to appear on public pages.
                </p>

                <a href="/resources.php" target="_blank" rel="noopener">
                    View public resources →
                </a>
            </article>


            <article class="card">
                <p class="eyebrow">Add information</p>

                <h2>New source</h2>

                <p>
                    Add a webpage, PDF, form, handbook,
                    workbook, portal, or other source.
                </p>

                <a href="/admin/sources/create.php">
                    Add a source →
                </a>
            </article>

        </div>

    </div>
</section>


<section class="section muted">
    <div class="container narrow">

        <div class="callout">

            <p class="eyebrow">Next step</p>

            <h2>Move the existing source log into the database</h2>

            <p>
                Once the source form and source list are working,
                the entries currently stored in
                <code>research/SOURCE_LOG.md</code>
                can be imported and assigned to counties, fairs,
                projects, topics, and age groups.
            </p>

        </div>

    </div>
</section>

<?php

require_once __DIR__
    . '/../../app/includes/admin-footer.php';