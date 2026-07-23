<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/log.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
$id = is_array($payload) && isset($payload['id']) ? (string) $payload['id'] : '';

if ($id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing project id.']);
    exit;
}

try {
    $project = find_project($id);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

if ($project === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Project not found.']);
    exit;
}

$path = $project['log'];

if (!file_exists($path)) {
    // Nothing to clear — treat as success and return empty log state.
    echo json_encode([
        'ok' => true,
        'cleared' => false,
        'project' => [
            'id' => $project['id'],
            'name' => $project['name'],
            'log' => $project['log'],
            'script' => $project['script'],
        ],
        'log' => read_log($path),
    ]);
    exit;
}

if (!is_writable($path)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => "Log file is not writable: {$path}"]);
    exit;
}

$handle = fopen($path, 'c');
if ($handle === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => "Unable to open log file: {$path}"]);
    exit;
}

$truncated = ftruncate($handle, 0);
fclose($handle);

if (!$truncated) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => "Unable to clear log file: {$path}"]);
    exit;
}

echo json_encode([
    'ok' => true,
    'cleared' => true,
    'project' => [
        'id' => $project['id'],
        'name' => $project['name'],
        'log' => $project['log'],
        'script' => $project['script'],
    ],
    'log' => read_log($path),
]);
