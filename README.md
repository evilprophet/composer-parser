# 📦 Composer Parser

## Introduction

Composer Parser is a Symfony Console application for comparing Composer dependencies across multiple repositories and publishing one consolidated report.

It loads repositories through Git or the GitLab API, reads `composer.json` and optionally `composer.lock`, groups packages by configurable rules, and writes the result to XLSX, JSON, HTML, or Google Sheets.

## ✨ Key Features

- **Multiple repository sources**: use a local Git checkout flow or download files and archives through the GitLab API.
- **Three parser modes**: compare declared constraints, installed versions, or the latest available versions reported by Composer.
- **Four report formats**: generate XLSX, JSON, HTML, or Google Sheets output from the same normalized data.
- **Configurable package groups**: match `require`, `require-dev`, `replace`, `patchset`, and explicitly observed packages with regular expressions.
- **Configurable report styling**: apply version- and package-based colors to XLSX, HTML, and Google Sheets reports.
- **Complete report snapshots**: repository and parser failures stop the run before the writer replaces the previous complete report.

## 📁 Project Structure

```text
.
├── .github/workflows/   # GitHub Actions workflows
├── bin/                 # CLI entrypoint
├── config/              # Parameters template and service configuration
├── examples/            # CI integration examples
├── src/                 # Application source code
│   ├── Api/             # Public contracts
│   ├── Command/         # Console commands
│   ├── Exception/       # Domain exceptions
│   ├── Model/           # Configuration and report models
│   └── Service/         # Providers, parsers, writers, validation, and application services
├── tests/               # Unit and integration tests
├── var/                 # Generated repositories, reports, and logs
├── composer.json        # PHP dependencies and project scripts
├── Dockerfile           # Production CLI image
└── phpunit.xml          # PHPUnit suites and strict failure rules
```

## 🛠️ Requirements

- PHP `8.4+`
- Composer `2+`
- PHP extensions required by the locked dependencies, including `ext-curl`, `ext-gd`, `ext-json`, and `ext-zip`
- Git available in `PATH` when using `gitRepository`
- Composer available in `PATH` when using `composerFull`
- GitLab URL and API token when using a GitLab provider
- Google Cloud service account JSON when using `googleSheets`

## 🚀 Quick Start

Both methods below use `config/parameters.yaml`, created from `config/parameters.yaml.template`. Update it for your repositories and selected provider, parser, and writer. Run `app:cleanup` before every `app:run` so providers work with fresh repository data.

Use `-p <path>`, `--parameters-file <path>`, or `--parameters-file=<path>` to select a different parameters file. Relative paths are resolved from the current working directory.

### Docker

The container reads the mounted parameters file from `/config/parameters.yaml` and persists downloaded repositories, reports, and logs in `/app/var`:

```bash
cp config/parameters.yaml.template config/parameters.yaml
mkdir -p var
docker run --rm --volume "$PWD/config/parameters.yaml:/config/parameters.yaml:ro" --volume "$PWD/var:/app/var" ghcr.io/evilprophet/composer-parser:latest app:cleanup -p /config/parameters.yaml
docker run --rm --volume "$PWD/config/parameters.yaml:/config/parameters.yaml:ro" --volume "$PWD/var:/app/var" ghcr.io/evilprophet/composer-parser:latest app:run -p /config/parameters.yaml
```

### Manual

Clone the repository, install dependencies, create the parameters file, and run the application:

```bash
git clone https://github.com/evilprophet/composer-parser.git
cd composer-parser
composer install
cp config/parameters.yaml.template config/parameters.yaml
bin/console app:cleanup
bin/console app:run
```

## ⚙️ Configuration

`config/parameters.yaml.template` is the configuration reference. Every report run requires these base keys:

- `app.config.timezone`
- `app.config.providerType`
- `app.config.parserType`
- `app.config.writerType`
- `package.config.includeInstalledVersion`
- `package.config.installedVersionDisplayedIn`
- `package.config.packageGroups`
- `repository.config.repositoryList`
- `writer.config.local.fileName`
- `writer.config.local.fileDirectory`
- `writer.config.shared.sheetName`

**Providers**

- `gitRepository` clones the configured remote or reuses an existing checkout, then checks out the configured branch without running `fetch` or `pull`.
- `gitlabApiFiles` downloads `composer.json` and the optional `composer.lock` directly from GitLab.
- `gitlabApiArchive` downloads a GitLab repository archive and optionally decrypts `auth.json.encrypted` with `app.config.ansibleVaultPassword`.

**Parsers**

- `composerJson` reads `require`, `require-dev`, `replace`, and `extra.patchset` data from `composer.json`.
- `composerJsonAndLock` also reads installed versions from both `packages` and `packages-dev` in `composer.lock`.
- `composerFull` additionally runs `composer outdated --no-plugins --no-scripts --format=json` inside each downloaded repository to collect latest available versions.

`composer.lock` is optional. Without it, lock-aware parsers still report declared constraints but cannot add installed or observed package versions.

**Writers**

- `xlsx` writes a styled workbook to `writer.config.local.fileDirectory`.
- `json` writes a structured local report without styling.
- `html` writes a standalone styled report.
- `googleSheets` clears and rewrites the configured sheet through the Google Sheets API.

Styling is required for `xlsx`, `html`, and `googleSheets`. An XLSX sheet name cannot contain `*`, `:`, `/`, `\`, `?`, `[` or `]`.

**Package groups**

- `groupType` accepts `require`, `require-dev`, `replace`, `patchset`, or `observed`.
- Higher `parserPriority` claims matching `require`, `require-dev`, and `replace` packages first. `observed` and `patchset` entries are added to every matching group.
- Lower `writerOrder` is displayed first; equal values preserve configuration order.
- An `observed` group requires `composerJsonAndLock` or `composerFull`, `includeInstalledVersion: true`, and at least one package in `observedPackages`.

**Repository entries**

- Every entry requires `name`, `directory`, `remote`, and `branch`; only `name` and `directory` must be unique.
- `directory` must be a normalized relative child of `var/repositories/` without `.` or `..` segments or a trailing slash.
- `remote` must use SCP-style SSH or an HTTP, HTTPS, or SSH URL. GitLab API providers extract the `namespace/project` path from this value.
- Report columns are sorted alphabetically by repository name, independently of configuration order.
- Standard dependency and observed package rows are sorted alphabetically; patch rows preserve their source order.

**Google Sheets**

- Enable the Google Sheets API in a Google Cloud project.
- Create a service account and download its JSON key outside the repository.
- Share the target spreadsheet with the service account email as `Editor`.
- Set `writer.config.googleSheets.spreadsheetId` and `writer.config.googleSheets.serviceAccountJsonPath`.
- Never commit API tokens, vault passwords, or service account files.

## 🔄 Report Flow

1. `app:cleanup` removes only the configured working directories below `var/repositories/`.
2. The selected provider loads every configured repository into its local working directory.
3. The selected parser builds one package matrix across all repositories.
4. The selected writer publishes the report only after every repository has been processed successfully.
5. A repository, parser, or validation failure returns a non-zero exit code and leaves the previous complete report untouched.

## 💻 Commands Overview

| Command                   | Description                                                        |
|---------------------------|--------------------------------------------------------------------|
| `bin/console app:cleanup` | Remove configured local repository working directories.            |
| `bin/console app:run`     | Load repositories, parse dependencies, and publish one report.     |
| `bin/console list`        | List available commands without initializing unused integrations.  |

Command exit codes:

- `0` - command completed successfully.
- `1` - configuration, provider, parser, writer, or cleanup failed at runtime.
- `2` - CLI bootstrap failed, for example because the parameters file does not exist.

## 🧪 Testing & Quality

Run the complete PHPUnit suite and the same PHP_CodeSniffer rules used by CI:

```bash
composer test
vendor/bin/phpcs --standard=PSR12 --extensions=php --warning-severity=0 src tests
```

GitLab CI runs the `Unit` and `Integration` PHPUnit suites separately on PHP 8.4 and rejects warnings, notices, deprecations, risky tests, and unexpected test output.

## 🧭 Notes

- Local report names use `writer.config.local.fileName`; `{date}` is replaced with the current date in `Y-m-d` format and the writer appends the extension.
- Relative local output and service account paths are resolved from the current working directory.
- Local output directories are created automatically, and existing files with the same generated name are overwritten.
- Runtime errors handled by `app:cleanup` and `app:run` are logged to `var/log/error.log`; bootstrap errors are written directly to STDERR.
- `app:run` does not perform cleanup automatically; the supported operational sequence is always `app:cleanup` followed by `app:run`.
- Run cleanup and report generation sequentially. Concurrent executions against the same working directories are not supported.
- Google Sheets output is updated in place; an API failure during the writer step can leave the target sheet empty or partially updated.
