# 📦 Composer Parser

## ✨ Introduction

Composer Parser is a CLI tool for comparing dependencies across multiple repositories. It pulls `composer.json` (optionally `composer.lock`), groups packages by configurable rules, and generates a single XLSX report for easy comparison.

## ✅ Key Features

- Aggregates data from multiple repositories into one report.
- Supports three parser modes: `composerJson`, `composerJsonAndLock`, `composerFull`.
- Multiple data sources: local Git, GitLab API (files/zip archive).
- Configurable package groups and cell styling rules.
- XLSX output with comments for installed and latest versions.

## 🧩 Requirements

- PHP >= 8.3
- PHP extensions: `ext-json`, `ext-zip`
- Git (only for `providerType: gitRepository`)
- `composer` in PATH (required for `parserType: composerFull`)
- GitLab Personal Access Token with `read_repository` (for GitLab providers)
- Ansible Vault password (optional, only for `gitlabApiArchive` + `auth.json.encrypted`)

## 🛠️ Installation

1. Create the project via Composer:
   ```bash
   composer create-project evilstudio/composer-parser
   ```
2. Copy the configuration template:
   ```bash
   cp config/parameters.yaml.template config/parameters.yaml
   ```
3. Update values in `config/parameters.yaml`.
4. Run the CLI commands (see the Commands section).

## ⚙️ Configuration (config/parameters.yaml)

All fields are documented in `config/parameters.yaml.template`. Copy it and fill in values.

## 💻 Commands

| Command       | Description                                                   |
|---------------|---------------------------------------------------------------|
| `app:run`     | Fetches data from repositories and generates the XLSX report. |
| `app:cleanup` | Removes downloaded repositories from the working directory.   |

## 🗺️ Roadmap

See `docs/roadmap.md`.

## 📄 Output

- The XLSX file is written to `writer.config.local.fileDirectory`.
- `{date}` in the filename is replaced with the current date (`Y-m-d`).
- The sheet header includes a "Last update" timestamp.
- With `includeInstalledVersion=true`, comments include versions from `composer.lock`.
- In `composerFull`, a comment includes the latest version from `composer outdated`.

## 📁 Project Structure

```
bin/          # CLI entry script
config/       # Configuration (parameters, services)
docker/       # Docker settings (xdebug)
src/          # Application source code
├── Api/      # Interfaces
├── Command/  # CLI commands
├── Exception/  # Exceptions
├── Model/      # Data models
└── Service/    # Parsers, providers, writer
var/          # Working data (repositories, results)
vendor/       # Composer dependencies
```

## 📝 Notes

- `parserType: composerJson` does not use `composer.lock`.
- `parserType: composerJsonAndLock` and `composerFull` expect a valid `composer.lock`.
- `composerFull` runs `composer outdated --format=json` in each repository directory.
- The Dockerfile uses `php:8.3-cli`; if you rely on Docker, keep it aligned with project requirements.
