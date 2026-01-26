# 🗺️ Roadmap

## ✅ Phase 1 - UX and Reliability

- [ ] Improve CLI feedback and errors (missing tokens, invalid branches, API failures)
- [ ] Validate configuration on startup (required fields, regex correctness, directories)
- [ ] Add structured logging to `var/log` with verbose mode
- [ ] Add exit codes and summary statistics (projects processed, skipped, failed)

## 📤 Phase 2 - Output and Integrations

- [ ] Add JSON export (machine-readable, easy to transform)
- [ ] Add HTML report (quick human-readable summary)
- [ ] Add direct Google Sheets export (service account integration)
- [ ] Preserve XLSX as default output

## ⚡ Phase 3 - Performance and Scale

- [ ] Cache downloaded repositories/archives with TTL
- [ ] Add optional parallel processing for multiple repositories
- [ ] Add GitLab API rate-limit handling and retries
- [ ] Make provider selection per repository (mixed sources)

## 🧩 Phase 4 - Extensibility

- [ ] Plugin-style writers (XLSX, JSON, HTML, Google Sheets)
- [ ] Plugin-style providers (GitHub API, Bitbucket)
- [ ] Custom group templates for common ecosystems
