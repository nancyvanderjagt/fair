<?php

declare(strict_types=1);

function csrf_token(string $formName): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException(
            'A session must be active before creating a CSRF token.'
        );
    }

    if (
        !isset($_SESSION['csrf_tokens'])
        || !is_array($_SESSION['csrf_tokens'])
    ) {
        $_SESSION['csrf_tokens'] = [];
    }

    if (
        !isset($_SESSION['csrf_tokens'][$formName])
        || !is_string($_SESSION['csrf_tokens'][$formName])
    ) {
        $_SESSION['csrf_tokens'][$formName] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_tokens'][$formName];
}

function csrf_input(string $formName): string
{
    return sprintf(
        '<input type="hidden" name="csrf_token" value="%s">',
        htmlspecialchars(
            csrf_token($formName),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}

function verify_csrf(string $formName): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    $storedToken = $_SESSION['csrf_tokens'][$formName] ?? '';

    if (
        !is_string($submittedToken)
        || !is_string($storedToken)
        || $storedToken === ''
        || !hash_equals($storedToken, $submittedToken)
    ) {
        http_response_code(403);
        exit('This form submission could not be verified.');
    }

    unset($_SESSION['csrf_tokens'][$formName]);
}