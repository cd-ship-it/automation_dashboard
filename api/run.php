<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/config.php';
require_once dirname(__DIR__) . '/lib/job.php';

header('Content-Type: application/json; charset=utf-8');

// Allow long-running weekly scripts (adjust if needed).
set_time_limit(0);
ignore_user_abort(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
$id = is_array($payload) && isset($payload['id']) ? (string) $payload['id'] : '';
$command_id = is_array($payload) && isset($payload['command_id']) ? (string) $payload['command_id'] : '';
$input_text = is_array($payload) && isset($payload['input']) ? (string) $payload['input'] : '';

if ($id === '' || $command_id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing project id or command_id.']);
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

$command = null;
foreach ($project['commands'] as $candidate) {
    if ($candidate['id'] === $command_id) {
        $command = $candidate;
        break;
    }
}

if ($command === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Command not found.']);
    exit;
}

$script = $project['script'];

if (!file_exists($script)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => "Script not found: {$script}"]);
    exit;
}

if (!is_executable($script)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => "Script is not executable: {$script}"]);
    exit;
}

if ($command['input'] !== null && trim($input_text) === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Input is required for this command.']);
    exit;
}

if ($project['project_root'] !== null) {
    if (!is_dir($project['project_root'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "project_root is not a directory: {$project['project_root']}"]);
        exit;
    }
    if (!chdir($project['project_root'])) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => "Unable to chdir to project_root: {$project['project_root']}"]);
        exit;
    }
}

$started = microtime(true);
$output = [];
$exit_code = 0;

$arg_parts = [];
foreach ($command['args'] as $arg) {
    $arg_parts[] = escapeshellarg($arg);
}

// Optional free-text input is appended as a double-quoted shell argument.
if ($command['input'] !== null) {
    $arg_parts[] = shell_double_quote($input_text);
}

// Run only the configured script path + args; never accept arbitrary shell input.
$command_line = escapeshellarg($script);
if ($arg_parts !== []) {
    $command_line .= ' ' . implode(' ', $arg_parts);
}

$queued = $project['use_php_queue'];
$pid = null;

if ($queued) {
    // Detach with nohup so PHP can return while the script keeps running
    // (shared hosts like SiteGround do not provide `at` / atd).
    // Write exit code to a sidecar status file when finished for polling.
    $status_path = run_status_path($project['log']);
    write_run_status_running($status_path);

    $wrapper = $command_line . '; echo $? > ' . escapeshellarg($status_path);
    exec(
        'nohup bash -c ' . escapeshellarg($wrapper) . ' > /dev/null 2>&1 & echo $!',
        $output,
        $exit_code
    );

    if (isset($output[0]) && preg_match('/^\d+$/', trim($output[0]))) {
        $pid = (int) trim($output[0]);
    }
} else {
    exec($command_line . ' 2>&1', $output, $exit_code);
}

$elapsed_ms = (int) round((microtime(true) - $started) * 1000);

echo json_encode([
    'ok' => $exit_code === 0,
    'queued' => $queued,
    'pid' => $pid,
    'exit_code' => $exit_code,
    'elapsed_ms' => $elapsed_ms,
    'output' => implode("\n", $output),
    'project' => [
        'id' => $project['id'],
        'name' => $project['name'],
        'script' => $project['script'],
        'use_php_queue' => $project['use_php_queue'],
    ],
    'command' => [
        'id' => $command['id'],
        'label' => $command['label'],
        'args' => $command['args'],
    ],
]);
