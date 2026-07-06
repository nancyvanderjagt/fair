<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../../app/auth/require-login.php';

require_once __DIR__
    . '/../../../app/services/UrlMetadataService.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'message' => 'Only POST requests are allowed.',
    ]);

    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
$storedToken = $_SESSION['source_fetch_csrf_token'] ?? '';

if (
    !is_string($submittedToken)
    || !is_string($storedToken)
    || $storedToken === ''
    || !hash_equals($storedToken, $submittedToken)
) {
    http_response_code(403);

    echo json_encode([
        'ok' => false,
        'message' =>
            'The fetch request could not be verified.',
    ]);

    exit;
}

$url = trim((string) ($_POST['url'] ?? ''));

try {
    $service = new UrlMetadataService();
    $data = $service->fetch($url);

    echo json_encode(
        [
            'ok' => true,
            'data' => $data,
        ],
        JSON_THROW_ON_ERROR
    );
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log(
        'Source metadata fetch failed: '
        . $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' =>
            'The source could not be analyzed. You can still enter it manually.',
    ]);
}