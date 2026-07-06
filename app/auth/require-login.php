<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

$adminUserId = $_SESSION['admin_user_id'] ?? null;

if (!is_int($adminUserId) && !ctype_digit((string) $adminUserId)) {
    header('Location: /admin/login.php');
    exit;
}

try {
    $statement = db()->prepare(
        'SELECT
            id,
            email,
            display_name,
            role,
            is_active
         FROM admin_users
         WHERE id = :id
         LIMIT 1'
    );

    $statement->execute([
        'id' => (int) $adminUserId,
    ]);

    $currentAdmin = $statement->fetch();

    if (
        !is_array($currentAdmin)
        || (int) $currentAdmin['is_active'] !== 1
    ) {
        $_SESSION = [];
        session_destroy();

        header('Location: /admin/login.php');
        exit;
    }
} catch (Throwable $exception) {
    error_log(
        'Admin authentication error: '
        . $exception->getMessage()
    );

    http_response_code(503);
    exit('The administration area is temporarily unavailable.');
}

if (
    !isset($_SESSION['logout_csrf_token'])
    || !is_string($_SESSION['logout_csrf_token'])
) {
    $_SESSION['logout_csrf_token'] = bin2hex(random_bytes(32));
}