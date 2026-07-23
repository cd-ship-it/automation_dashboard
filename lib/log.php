<?php

declare(strict_types=1);

/**
 * Read a log file, optionally limited to the last N lines.
 *
 * @return array{exists: bool, content: string, path: string, size: int|null, modified: int|null, truncated: bool}
 */
function read_log(string $path, int $max_lines = 2000): array
{
    $result = [
        'exists' => false,
        'content' => '',
        'path' => $path,
        'size' => null,
        'modified' => null,
        'truncated' => false,
    ];

    if (!file_exists($path) || !is_readable($path)) {
        return $result;
    }

    $result['exists'] = true;
    $result['size'] = filesize($path) ?: 0;
    $result['modified'] = filemtime($path) ?: null;

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return $result;
    }

    if (count($lines) > $max_lines) {
        $lines = array_slice($lines, -$max_lines);
        $result['truncated'] = true;
        array_unshift($lines, "… showing last {$max_lines} lines …", '');
    }

    $result['content'] = implode("\n", $lines);
    return $result;
}
