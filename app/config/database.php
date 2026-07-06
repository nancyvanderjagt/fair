<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configPath = dirname(__DIR__, 2) . '/private/database.php';

    if (!is_file($configPath)) {
        throw new RuntimeException(
            'The private database configuration file was not found.'
        );
    }

    $config = require $configPath;

    $requiredFields = [
        'host',
        'port',
        'database',
        'username',
        'password',
    ];

    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $config)) {
            throw new RuntimeException(
                "The database configuration is missing: {$field}"
            );
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    );

    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}