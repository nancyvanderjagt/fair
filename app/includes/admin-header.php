<?php

declare(strict_types=1);

$adminPageTitle = $adminPageTitle ?? 'Admin';

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
        <?= htmlspecialchars(
            $adminPageTitle,
            ENT_QUOTES,
            'UTF-8'
        ) ?> | Fair Admin
    </title>

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
            href="/admin/"
            aria-label="Fair Admin dashboard"
        >
            <span
                class="brand-mark"
                aria-hidden="true"
            >
                4H
            </span>

            <span>
                <strong>Fair Admin</strong>
                <small>
                    Signed in as
                    <?= htmlspecialchars(
                        $currentAdmin['display_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </small>
            </span>
        </a>

        <nav class="site-nav" aria-label="Admin navigation">
            <a href="/admin/">Dashboard</a>

            <a href="/admin/sources/">Sources</a>

            <a href="/admin/sources/create.php">
                Add Source
            </a>

            <a href="/" target="_blank" rel="noopener">
                View Website
            </a>

            <form
                method="post"
                action="/admin/logout.php"
                style="display: inline;"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['logout_csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <button
                    type="submit"
                    class="button secondary"
                >
                    Log out
                </button>
            </form>
        </nav>

    </div>
</header>

<main>