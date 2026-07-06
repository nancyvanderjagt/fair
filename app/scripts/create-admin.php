<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';

function ask(string $question): string
{
    echo $question;

    $answer = fgets(STDIN);

    if ($answer === false) {
        throw new RuntimeException('Unable to read the response.');
    }

    return trim($answer);
}

function askForPassword(string $question): string
{
    echo $question;

    $sttyState = null;

    if (function_exists('shell_exec')) {
        $sttyState = shell_exec('stty -g 2>/dev/null');

        if (is_string($sttyState) && trim($sttyState) !== '') {
            shell_exec('stty -echo');
        }
    }

    $password = fgets(STDIN);

    if (is_string($sttyState) && trim($sttyState) !== '') {
        shell_exec('stty ' . escapeshellarg(trim($sttyState)));
    }

    echo PHP_EOL;

    if ($password === false) {
        throw new RuntimeException('Unable to read the password.');
    }

    return rtrim($password, "\r\n");
}

try {
    $displayName = ask('Display name: ');
    $email = strtolower(ask('Email address: '));

    if ($displayName === '') {
        throw new RuntimeException('Display name is required.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }

    $password = askForPassword(
        'Password — minimum 12 characters: '
    );

    $confirmation = askForPassword(
        'Confirm password: '
    );

    if (strlen($password) < 12) {
        throw new RuntimeException(
            'The password must contain at least 12 characters.'
        );
    }

    if (!hash_equals($password, $confirmation)) {
        throw new RuntimeException('The passwords do not match.');
    }

    $pdo = db();

    $existingUser = $pdo->prepare(
        'SELECT id
         FROM admin_users
         WHERE email = :email
         LIMIT 1'
    );

    $existingUser->execute([
        'email' => $email,
    ]);

    if ($existingUser->fetchColumn() !== false) {
        throw new RuntimeException(
            'An administrator with that email already exists.'
        );
    }

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $insertUser = $pdo->prepare(
        'INSERT INTO admin_users (
            email,
            display_name,
            password_hash,
            role,
            is_active
        ) VALUES (
            :email,
            :display_name,
            :password_hash,
            :role,
            1
        )'
    );

    $insertUser->execute([
        'email' => $email,
        'display_name' => $displayName,
        'password_hash' => $passwordHash,
        'role' => 'owner',
    ]);

    echo "Administrator account created successfully.\n";
    echo "Email: {$email}\n";
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Administrator creation failed: "
        . $exception->getMessage()
        . PHP_EOL
    );

    exit(1);
}