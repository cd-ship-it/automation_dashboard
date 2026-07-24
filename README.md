# Automation Dashboard

PHP dashboard for weekly automation tasks: view project logs and manually re-run shell scripts.

## Setup

1. Point your PHP web server document root at this directory (PHP 8+ recommended).
2. Edit `config.json` — one object per project `id`, with shared paths and a `commands` list:

```json
{
  "prod_path": "/home/customer/www/crosspointchurchsv.org/scripts/proj",
  "dev_path": "/Users/fredng/projects",
  "projects": [
    {
      "id": "Heart-of-Shepherd",
      "name": "Heart of Shepherd",
      "project_root": "{base}/heart",
      "log": "{base}/heart/logs/heart_finance_log.txt",
      "script": "{base}/heart/run_heart_finance.sh",
      "commands": [
        {
          "id": "run",
          "label": "Run",
          "args": ["--force"]
        }
      ]
    }
  ]
}
```

If `prod_path` exists as a directory, that is used as `{base}`; otherwise `{base}` is `dev_path`.

| Level | Fields |
|-------|--------|
| Root | `prod_path`, `dev_path` |
| Project | `id`, `name`, `log`, `script`, optional `project_root`, optional `use_php_queue` |
| Command | `id`, `label`, optional `args`, optional `input` |

- Use `{base}` in path fields (also `{prod_path}` / `{dev_path}` if needed).
- Shared `log` / `script` / `project_root` live on the project (do not repeat per command).
- Optional `args` are passed to the script on Run.
- Optional `input` (string) shows a text box prefilled with that value. On Run it is appended as a double-quoted argument, e.g. `script.sh --create "Winky Tang"`.
- Optional `project_root`: the runner `chdir`s there before executing the script.
- Optional `use_php_queue` (`true`/`false`, default `false`): instead of running the script inline and waiting, the job is started in the background with `nohup <script + args> > /dev/null 2>&1 &`. The request returns as soon as the process is spawned (response includes the PID). Watch the project's log file for progress. Works on shared hosts like SiteGround that do not provide `at`.

3. Ensure the web server user can:
   - **read** each `log` file
   - **write** each `log` file (for Clear log)
   - **execute** each `script` file (`chmod +x script.sh`)

## Local preview

```bash
php -S localhost:8080
```

Then open http://localhost:8080

## Behavior

- Each project is one card (2 columns on desktop, 1 on mobile).
- Each command is a row with its own **Run** button (and optional input).
- The log pane is shared per project (~8 lines, scrollbars, scrolled to the latest line).
- **Refresh** reloads that project's log without re-running.
- **Clear log** truncates the log file to empty.
- **Run** executes `script` + command `args` (+ quoted input when configured), then reloads the log.

Only scripts listed in `config.json` can be run. Arbitrary commands are not accepted.
