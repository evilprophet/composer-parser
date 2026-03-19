# 📦 Composer Parser

## Introduction

Composer Parser is a CLI tool for comparing Composer dependencies across multiple repositories.
It collects `composer.json` (optionally `composer.lock`), groups packages by configurable rules, and exports a consolidated report.

## ✨ Key Features

- `🔎 Multi-repository parsing`
    - Reads dependency data from many repositories into one report.

- `🧠 Multiple parser modes`
    - `composerJson`
    - `composerJsonAndLock`
    - `composerFull` (includes latest versions via `composer outdated`)

- `🌐 Multiple providers`
    - `gitRepository`
    - `gitlabApiFiles`
    - `gitlabApiArchive`

- `📤 Multiple output writers`
    - `xlsx`
    - `json`
    - `html`

- `🧩 Configurable grouping and styling`
    - Regex-based package groups
    - Version-based cell styling (used in XLSX and HTML)

## 📁 Project Structure

```text
.
├── bin/             # CLI entrypoint
├── config/          # Parameters and service configuration
├── docs/            # Roadmap and refactor plan
├── src/             # Application source code
│   ├── Api/             # Contracts
│   ├── Command/         # CLI commands
│   ├── Exception/       # Domain exceptions
│   ├── Model/           # Data models
│   └── Service/         # App use-cases, parsers, providers, writers, config/report/log services
├── tests/           # Unit and integration tests
└── var/             # Working data (repositories, results, logs)
```

## 🚀 Quick Start

### 1. Clone and install

```bash
git clone https://github.com/evilstudio/composer-parser.git
cd composer-parser
composer install
```

### 2. Prepare configuration

```bash
cp config/parameters.yaml.template config/parameters.yaml
```

Set at least:

- `app.config.providerType`
- `app.config.parserType`
- `app.config.writerType` (`xlsx`, `json`, `html`)
- `repository.config.repositoryList`

### 3. Run parser

```bash
bin/console app:run
```

### 4. Cleanup downloaded repositories

```bash
bin/console app:cleanup
```

## 💻 Commands

| Command       | Description                                                 |
|---------------|-------------------------------------------------------------|
| `app:run`     | Fetches data from configured repositories and writes report |
| `app:cleanup` | Removes downloaded repositories                             |

## 🧭 Notes

- Output file path is built from `writer.config.local.fileDirectory` + `fileName`.
- `{date}` in `fileName` is replaced with current date (`Y-m-d`).
- Errors are logged to `var/log/error.log`.
- `composerFull` requires `composer` available in PATH.
- `gitRepository` provider requires local `git`.

## 🧪 Testing

```bash
# Run all tests
./vendor/bin/phpunit --testdox
```
