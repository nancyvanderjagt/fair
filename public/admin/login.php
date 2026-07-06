<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/database.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

if (isset($_SESSION['admin_user_id'])) {
    header('Location: /admin/');
    exit;
}

if (
    !isset($_SESSION['login_csrf_token'])
    || !is_string($_SESSION['login_csrf_token'])
) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken)
        || !hash_equals(
            $_SESSION['login_csrf_token'],
            $submittedToken
        )
    ) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = strtolower(
            trim((string) ($_POST['email'] ?? ''))
        );

        $password = (string) ($_POST['password'] ?? '');

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL)
            || $password === ''
        ) {
            $error = 'Enter a valid email address and password.';
        } else {
            try {
                $pdo = db();

                $statement = $pdo->prepare(
                    'SELECT
                        id,
                        email,
                        display_name,
                        password_hash,
                        role,
                        is_active,
                        failed_login_attempts,
                        (
                            locked_until IS NOT NULL
                            AND locked_until > NOW()
                        ) AS is_locked
                    FROM admin_users
                    WHERE email = :email
                    LIMIT 1'
                );

                $statement->execute([
                    'email' => $email,
                ]);

                $user = $statement->fetch();

                $loginAllowed = is_array($user)
                    && (int) $user['is_active'] === 1
                    && (int) $user['is_locked'] === 0;

                $passwordIsValid = $loginAllowed
                    && password_verify(
                        $password,
                        $user['password_hash']
                    );

                if (!$passwordIsValid) {
                    if (is_array($user)) {
                        $failedUpdate = $pdo->prepare(
                            'UPDATE admin_users
                             SET
                                failed_login_attempts =
                                    CASE
                                        WHEN failed_login_attempts + 1 >= 5
                                        THEN 0
                                        ELSE failed_login_attempts + 1
                                    END,
                                locked_until =
                                    CASE
                                        WHEN failed_login_attempts + 1 >= 5
                                        THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                                        ELSE locked_until
                                    END
                             WHERE id = :id'
                        );

                        $failedUpdate->execute([
                            'id' => $user['id'],
                        ]);
                    }

                    $error = 'The email address or password is incorrect.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['admin_user_id'] = (int) $user['id'];
                    $_SESSION['admin_email'] = $user['email'];
                    $_SESSION['admin_display_name'] =
                        $user['display_name'];
                    $_SESSION['admin_role'] = $user['role'];

                    $successUpdate = $pdo->prepare(
                        'UPDATE admin_users
                         SET
                            failed_login_attempts = 0,
                            locked_until = NULL,
                            last_login_at = NOW()
                         WHERE id = :id'
                    );

                    $successUpdate->execute([
                        'id' => $user['id'],
                    ]);

                    unset($_SESSION['login_csrf_token']);

                    header('Location: /admin/');
                    exit;
                }
            } catch (Throwable $exception) {
                error_log(
                    'Admin login error: '
                    . $exception->getMessage()
                );

                $error =
                    'The login system is temporarily unavailable.';
            }
        }
    }
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

    <title>Admin Login | Fair</title>

    <link
        rel="stylesheet"
        href="/assets/css/styles.css"
    >
</head>

<body>

<main>

    <section class="page-hero">
        <div class="container narrow">

            <p class="eyebrow">Private administration</p>

            <h1>Fair Admin</h1>

            <p>
                Sign in to manage sources, research, and public information!!
            </p>

        </div>
    </section>


    <section class="section">
        <div class="container narrow">

            <div class="card">

                <h2>Sign in</h2>

                <?php if ($error !== ''): ?>
                    <div class="warning">
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/admin/login.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION['login_csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <p>
                        <label for="email">
                            <strong>Email address</strong>
                        </label>
                    </p>

                    <p>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="<?= htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            autocomplete="username"
                            required
                            style="width: 100%; padding: 12px;"
                        >
                    </p>

                    <p>
                        <label for="password">
                            <strong>Password</strong>
                        </label>
                    </p>

                    <p>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            style="width: 100%; padding: 12px;"
                        >
                    </p>

                    <p>
                        <button
                            class="button primary"
                            type="submit"
                        >
                            Sign in
                        </button>
                    </p>

                </form>

            </div>

        </div>
    </section>

</main>

</body>
</html>