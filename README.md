# Automation Dashboard

PHP dashboard for weekly automation tasks: view project logs and manually re-run shell scripts.

## Setup

1. Point your PHP web server document root at this directory (PHP 8+ recommended).
2. Edit `config.json` — one object per project `id`, with shared paths and a `commands` list:

```json
{
  "projects": [
    {
      "id": "Heart-of-Shepherd",
      "name": "Heart of Shepherd",
      "project_root": "/Users/you/projects/heart",
      "log": "/Users/you/projects/heart/logs/heart_finance_log.txt",
      "script": "/Users/you/projects/heart/run_heart_finance.sh",
      "commands": [
        {
          "id": "run",
          "label": "Run",
          "args": ["--force"]
        }
      ]
    },
    {
      "id": "Pastoral-and-staff-reports",
      "name": "Pastoral and staff reports",
      "project_root": "/Users/you/projects/heart",
      "log": "/Users/you/projects/heart/logs/pastoral_and_staff_reports_log.txt",
      "script": "/Users/you/projects/heart/run_pastoral_and_staff_reports.sh",
      "commands": [
        {
          "id": "create",
          "label": "Create New",
          "args": ["--create"],
          "input": "Winky Tang"
        },
        {
          "id": "update-template",
          "label": "Update Master Template",
          "args": ["--update-template"]
        }
      ]
    }
  ]
}
```

Paths work on macOS (`/Users/...`) and Linux (`/var/...`, `/home/...`, `/opt/...`).

| Level | Fields |
|-------|--------|
| Project | `id`, `name`, `log`, `script`, optional `project_root` |
| Command | `id`, `label`, optional `args`, optional `input` |

- Shared `log` / `script` / `project_root` live on the project (do not repeat per command).
- Optional `args` are passed to the script on Run.
- Optional `input` (string) shows a text box prefilled with that value. On Run it is appended as a double-quoted argument, e.g. `script.sh --create "Winky Tang"`.
- Optional `project_root`: the runner `chdir`s there before executing the script.

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
