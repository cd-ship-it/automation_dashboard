<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/log.php';

$config_error = null;
$projects = [];

try {
    $config = load_config();
    $projects = $config['projects'];
} catch (Throwable $e) {
    $config_error = $e->getMessage();
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$cards = [];
foreach ($projects as $project) {
    $path_errors = [];

    if (!file_exists($project['script'])) {
        $path_errors[] = 'Script not found: ' . $project['script'];
    } elseif (!is_file($project['script'])) {
        $path_errors[] = 'Script path is not a file: ' . $project['script'];
    }

    if (!file_exists($project['log'])) {
        $path_errors[] = 'Log file not found: ' . $project['log'];
    } elseif (!is_file($project['log'])) {
        $path_errors[] = 'Log path is not a file: ' . $project['log'];
    }

    if ($project['project_root'] !== null && !is_dir($project['project_root'])) {
        $path_errors[] = 'project_root not found: ' . $project['project_root'];
    }

    $cards[] = [
        'project' => $project,
        'log' => read_log($project['log']),
        'path_errors' => $path_errors,
        'script_ok' => file_exists($project['script']) && is_file($project['script']),
        'log_ok' => file_exists($project['log']) && is_file($project['log']),
    ];
}

$script_name = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$base_path = str_replace('\\', '/', dirname($script_name));
if ($base_path === '/' || $base_path === '.') {
    $base_path = '';
}
$css_v = (string) (@filemtime(__DIR__ . '/assets/dashboard.css') ?: time());
$js_v = (string) (@filemtime(__DIR__ . '/assets/dashboard.js') ?: time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Automation Dashboard</title>
  <link rel="stylesheet" href="<?= h($base_path) ?>/assets/dashboard.css?v=<?= h($css_v) ?>">
</head>
<body data-base="<?= h($base_path) ?>">
  <div class="wrap">
    <header>
      <div>
        <h1>Automation Dashboard</h1>
        <p class="subtitle">View logs and manually re-run weekly tasks</p>
      </div>
    </header>

    <?php if ($config_error !== null): ?>
      <p class="status err"><?= h($config_error) ?></p>
    <?php elseif ($cards === []): ?>
      <p class="status err">No projects configured. Edit <code>config.json</code>.</p>
    <?php else: ?>
      <div class="project-grid">
        <?php foreach ($cards as $card):
            $project = $card['project'];
            $log = $card['log'];
            $path_errors = $card['path_errors'];
            $has_path_errors = $path_errors !== [];
            ?>
          <article
            class="project-card<?= $has_path_errors ? ' has-path-errors' : '' ?>"
            data-project-id="<?= h($project['id']) ?>"
            data-script-ok="<?= $card['script_ok'] ? '1' : '0' ?>"
            data-log-ok="<?= $card['log_ok'] ? '1' : '0' ?>"
            data-queued="<?= $project['use_php_queue'] ? '1' : '0' ?>"
          >
            <div class="card-header">
              <h2><?= h($project['name']) ?></h2>
              <div class="card-actions">
                <button type="button" class="btn-secondary refresh-btn"<?= $card['log_ok'] ? '' : ' disabled' ?>>Refresh</button>
              </div>
            </div>

            <?php if ($has_path_errors): ?>
              <div class="path-errors" role="alert">
                <?php foreach ($path_errors as $error): ?>
                  <p class="status err"><?= h($error) ?></p>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="command-list">
              <?php foreach ($project['commands'] as $command):
                  $has_input = $command['input'] !== null;
                  ?>
                <div
                  class="command-row"
                  data-command-id="<?= h($command['id']) ?>"
                  data-has-input="<?= $has_input ? '1' : '0' ?>"
                >
                  <div class="command-main">
                    <span class="command-label"><?= h($command['label']) ?></span>
                    <?php if ($has_input): ?>
                      <input
                        type="text"
                        class="command-input"
                        value="<?= h($command['input']) ?>"
                        placeholder="Input"
                        autocomplete="off"
                        <?= $card['script_ok'] ? '' : 'disabled' ?>
                      >
                    <?php endif; ?>
                  </div>
                  <button type="button" class="btn-run run-btn"<?= $card['script_ok'] ? '' : ' disabled' ?>>Run</button>
                </div>
              <?php endforeach; ?>
            </div>

            <p class="status card-status" aria-live="polite"></p>

            <div class="log-panel">
              <div class="log-header">
                <span class="log-title">Log</span>
                <span class="log-header-right">
                  <span class="log-hint"><?= $card['log_ok'] ? ($log['truncated'] ? 'last 2000 lines' : 'full file') : 'missing' ?></span>
                  <a href="#" class="clear-log-link<?= $card['log_ok'] ? '' : ' is-disabled' ?>">Clear log</a>
                </span>
              </div>
              <pre class="log-content<?= $log['content'] === '' ? ' empty' : '' ?>"><?= h($log['content']) ?></pre>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($cards !== []): ?>
  <script src="<?= h($base_path) ?>/assets/dashboard.js?v=<?= h($js_v) ?>"></script>
  <?php endif; ?>
</body>
</html>
