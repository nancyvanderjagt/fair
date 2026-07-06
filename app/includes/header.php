<?php

$pageTitle = $pageTitle ?? 'Fair';
$pageDescription = $pageDescription
    ?? 'A clear path through 4-H projects and fair participation.';
$currentPage = $currentPage ?? '';

function nav_attributes(string $pageKey, string $currentPage): string
{
    return $pageKey === $currentPage
        ? ' aria-current="page"'
        : '';
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | Fair
    </title>

    <meta
        name="description"
        content="<?= htmlspecialchars(
            $pageDescription,
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

    <link
        rel="stylesheet"
        href="/assets/css/styles.css"
    >
</head>

<body>

<header class="site-header">
    <div class="container header-inner">

        <a
            class="brand"
            href="/"
            aria-label="Fair home"
        >
            <span
                class="brand-mark"
                aria-hidden="true"
            >
                4H
            </span>

            <span>
                <strong>Fair</strong>
                <small>A clear path through 4-H and fair</small>
            </span>
        </a>

        <button
            class="nav-toggle"
            type="button"
            aria-expanded="false"
            aria-controls="site-nav"
        >
            Menu
        </button>

        <nav
            id="site-nav"
            class="site-nav"
            aria-label="Main navigation"
        >
            <a
                href="/getting-started.php"
                <?= nav_attributes(
                    'getting-started',
                    $currentPage
                ) ?>
            >
                Getting Started
            </a>

            <a
                href="/projects.php"
                <?= nav_attributes(
                    'projects',
                    $currentPage
                ) ?>
            >
                Project Guides
            </a>

            <a
                href="/fair-week.php"
                <?= nav_attributes(
                    'fair-week',
                    $currentPage
                ) ?>
            >
                Fair Week
            </a>

            <a
                href="/ionia-county.php"
                <?= nav_attributes(
                    'ionia-county',
                    $currentPage
                ) ?>
            >
                Ionia County
            </a>

            <a
                href="/resources.php"
                <?= nav_attributes(
                    'resources',
                    $currentPage
                ) ?>
            >
                Resources
            </a>

            <a
                href="/updates.php"
                <?= nav_attributes(
                    'updates',
                    $currentPage
                ) ?>
            >
                Updates
            </a>
        </nav>

    </div>
</header>