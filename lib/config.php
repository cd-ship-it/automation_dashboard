<?php

declare(strict_types=1);

/**
 * Load and validate dashboard config.
 *
 * @return array{projects: list<array{id: string, name: string, project_root: string|null, log: string, script: string, commands: list<array{id: string, label: string, args: list<string>, input: string|null}>}>}
 */
function load_config(): array
{
    $path = dirname(__DIR__) . '/config.json';

    if (!is_readable($path)) {
        throw new RuntimeException('config.json is missing or unreadable.');
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Unable to read config.json.');
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['projects']) || !is_array($data['projects'])) {
        throw new RuntimeException('config.json must contain a "projects" array.');
    }

    $projects = [];
    $seen_ids = [];

    foreach ($data['projects'] as $index => $project) {
        if (!is_array($project)) {
            throw new RuntimeException("Project at index {$index} is invalid.");
        }

        foreach (['id', 'name', 'log', 'script'] as $field) {
            if (!isset($project[$field]) || !is_string($project[$field]) || $project[$field] === '') {
                throw new RuntimeException("Project at index {$index} is missing \"{$field}\".");
            }
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $project['id'])) {
            throw new RuntimeException("Project id \"{$project['id']}\" must be alphanumeric, underscore, or hyphen.");
        }

        if (isset($seen_ids[$project['id']])) {
            throw new RuntimeException("Duplicate project id \"{$project['id']}\".");
        }
        $seen_ids[$project['id']] = true;

        $project_root = null;
        if (isset($project['project_root'])) {
            if (!is_string($project['project_root']) || $project['project_root'] === '') {
                throw new RuntimeException("Project \"{$project['id']}\" project_root must be a non-empty string.");
            }
            $project_root = $project['project_root'];
        }

        if (!isset($project['commands']) || !is_array($project['commands']) || $project['commands'] === []) {
            throw new RuntimeException("Project \"{$project['id']}\" must have a non-empty commands array.");
        }

        $commands = [];
        $seen_command_ids = [];

        foreach ($project['commands'] as $cmd_index => $command) {
            if (!is_array($command)) {
                throw new RuntimeException("Project \"{$project['id']}\" command at index {$cmd_index} is invalid.");
            }

            foreach (['id', 'label'] as $field) {
                if (!isset($command[$field]) || !is_string($command[$field]) || $command[$field] === '') {
                    throw new RuntimeException("Project \"{$project['id']}\" command at index {$cmd_index} is missing \"{$field}\".");
                }
            }

            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $command['id'])) {
                throw new RuntimeException("Command id \"{$command['id']}\" must be alphanumeric, underscore, or hyphen.");
            }

            if (isset($seen_command_ids[$command['id']])) {
                throw new RuntimeException("Project \"{$project['id']}\" has duplicate command id \"{$command['id']}\".");
            }
            $seen_command_ids[$command['id']] = true;

            $args = [];
            if (isset($command['args'])) {
                if (!is_array($command['args'])) {
                    throw new RuntimeException("Command \"{$command['id']}\" args must be an array of strings.");
                }
                foreach ($command['args'] as $arg) {
                    if (!is_string($arg) || $arg === '' || !preg_match('/^[a-zA-Z0-9_.=\/-]+$/', $arg)) {
                        throw new RuntimeException("Command \"{$command['id']}\" has an invalid arg. Use simple flags like --force.");
                    }
                    $args[] = $arg;
                }
            }

            $input = null;
            if (array_key_exists('input', $command)) {
                if (!is_string($command['input'])) {
                    throw new RuntimeException("Command \"{$command['id']}\" input must be a string (prefilled text box value).");
                }
                $input = $command['input'];
            }

            $commands[] = [
                'id' => $command['id'],
                'label' => $command['label'],
                'args' => $args,
                'input' => $input,
            ];
        }

        $projects[] = [
            'id' => $project['id'],
            'name' => $project['name'],
            'project_root' => $project_root,
            'log' => $project['log'],
            'script' => $project['script'],
            'commands' => $commands,
        ];
    }

    return ['projects' => $projects];
}

/**
 * @return array{id: string, name: string, project_root: string|null, log: string, script: string, commands: list<array{id: string, label: string, args: list<string>, input: string|null}>}|null
 */
function find_project(string $id): ?array
{
    $config = load_config();
    foreach ($config['projects'] as $project) {
        if ($project['id'] === $id) {
            return $project;
        }
    }

    return null;
}

/**
 * @return array{id: string, label: string, args: list<string>, input: string|null}|null
 */
function find_command(string $project_id, string $command_id): ?array
{
    $project = find_project($project_id);
    if ($project === null) {
        return null;
    }

    foreach ($project['commands'] as $command) {
        if ($command['id'] === $command_id) {
            return $command;
        }
    }

    return null;
}

/**
 * Wrap a value in double quotes for the shell, escaping inner special chars.
 */
function shell_double_quote(string $value): string
{
    $escaped = str_replace(
        ['\\', '"', '$', '`'],
        ['\\\\', '\\"', '\\$', '\\`'],
        $value
    );

    return '"' . $escaped . '"';
}
