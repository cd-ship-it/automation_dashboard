<?php

declare(strict_types=1);

/**
 * Sidecar file next to the project log that tracks a background run.
 * Contents: "running" while active, then a numeric exit code when finished.
 */
function run_status_path(string $log_path): string
{
    return $log_path . '.run_status';
}

function process_is_running(int $pid): bool
{
    if ($pid <= 0) {
        return false;
    }

    if (function_exists('posix_kill')) {
        return @posix_kill($pid, 0);
    }

    $out = [];
    $code = 1;
    exec('kill -0 ' . $pid . ' 2>/dev/null', $out, $code);

    return $code === 0;
}

/**
 * @return array{state: string, exit_code: int|null, running: bool}
 */
function read_run_status(string $status_path, ?int $pid = null): array
{
    $pid_alive = $pid !== null && process_is_running($pid);
    $raw = '';

    if (is_readable($status_path)) {
        $raw = trim((string) file_get_contents($status_path));
    }

    if ($raw === 'running') {
        return [
            'state' => 'running',
            'exit_code' => null,
            'running' => true,
        ];
    }

    if ($raw !== '' && preg_match('/^-?\d+$/', $raw)) {
        $exit_code = (int) $raw;

        return [
            'state' => $exit_code === 0 ? 'ok' : 'error',
            'exit_code' => $exit_code,
            // Prefer status file once written; pid may briefly still look alive.
            'running' => false,
        ];
    }

    // No usable status file yet — fall back to pid liveness.
    if ($pid_alive) {
        return [
            'state' => 'running',
            'exit_code' => null,
            'running' => true,
        ];
    }

    return [
        'state' => 'unknown',
        'exit_code' => null,
        'running' => false,
    ];
}

function write_run_status_running(string $status_path): void
{
    $dir = dirname($status_path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($status_path, "running\n");
}
