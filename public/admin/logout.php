<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/require-login.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($submittedToken)
    || !hash_equals(
        $_SESSION['logout_csrf_token'],
        $submittedToken
    )
) {
    http_response_code(403);
    exit('The logout request could not be verified.');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookieParameters = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $cookieParameters['path'],
            'domain' => $cookieParameters['domain'],
            'secure' => $cookieParameters['secure'],
            'httponly' => $cookieParameters['httponly'],
            'samesite' => 'Lax',
        ]
    );
}

session_destroy();

header('Location: /admin/login.php');
exit;