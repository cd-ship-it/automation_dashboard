<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/log.php';
require_once dirname(__DIR__) . '/lib/job.php';
require_once dirname(__DIR__) . '/lib/http.php';

header('Content-Type: application/json; charset=utf-8');
send_no_cache_headers();

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
$pid = isset($_GET['pid']) && $_GET['pid'] !== '' ? (int) $_GET['pid'] : null;

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

$status_path = run_status_path($project['log']);
clearstatcache(true, $project['log']);
clearstatcache(true, $status_path);
$status = read_run_status($status_path, $pid);
$log = read_log($project['log']);

echo json_encode([
    'ok' => true,
    'project' => [
        'id' => $project['id'],
        'name' => $project['name'],
        'log' => $project['log'],
        'script' => $project['script'],
    ],
    'pid' => $pid,
    'pid_alive' => $pid !== null ? process_is_running($pid) : null,
    'running' => $status['running'],
    'state' => $status['state'],
    'exit_code' => $status['exit_code'],
    'log' => $log,
]);
