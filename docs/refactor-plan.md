# 🧭 Refactor Plan (proposed)

This plan keeps the current configuration format and preserves `Command` and `Exception` folders. The goal is to improve testability and make future features easier to add without changing behavior.

## 🎯 Goals

- Preserve existing config (`config/parameters.yaml`) and CLI commands.
- Improve testability by separating IO (providers/writers) from parsing logic.
- Keep array-based data flow, add lightweight wrappers/validation.
- Keep backward-compatible behavior and output.

## ✅ Constraints

- Keep `src/Command` and `src/Exception` as-is.
- Keep current DI approach with `services.yaml` + `parameters.yaml`.
- No breaking changes for existing users.

## 🧱 Target Structure (minimal change)

```
src/
├── Command/
├── Exception/
├── Api/
├── Model/
├── Service/
│   ├── App/          # use-cases: RunReport, CleanupRepositories
│   ├── Parser/       # parsing logic, no IO
│   ├── Provider/     # Git/GitLab integrations
│   ├── Writer/       # XLSX + future JSON/HTML/Sheets
│   ├── Config/       # config loader + validation
│   └── Report/       # report assembly + mapping
└── Support/          # shared helpers (Result, ReportSummary)
```

## 🧩 Refactor Phases

### Phase 0 — Baseline & Safety

- Add a lightweight smoke test or CLI dry-run (optional) to confirm output parity.
- Capture a sample XLSX output for comparison (manual or snapshot).

### Phase 1 — Lightweight Report Wrapper (No Behavior Change)

- Keep the existing array structure as the source of truth.
- Add a lightweight `Report` wrapper (object around array) for:
  - shape validation,
  - safe getters,
  - optional normalization.
- Update `Writer/Xlsx` to accept the wrapper (still backed by array).
- No deep DTO hierarchy; no change to parser output.

### Phase 2 — Extract App Use-Cases

- Add `Service/App/RunReport` that orchestrates:
  - Provider → Parser → ReportBuilder → Writer
- Add `Service/App/CleanupRepositories` for cleanup logic.
- Commands call use-cases instead of low-level services.

### Phase 3 — Clean Parser/Provider Boundaries

- Define `Api/RepositorySourceInterface` and `Api/ComposerFileReaderInterface`.
- Providers return typed `RepositoryData` (composer.json/lock + metadata).
- Parsers accept `RepositoryData` (no filesystem calls).

### Phase 4 — Config Validation

- Add `Service/Config/ConfigValidator`.
- Validate required fields, regex syntax, directory values, and supported enum values.
- Fail early with clear error messages.

### Phase 5 — Report Builder & Summaries

- Add `Service/Report/ReportBuilder` to build the report array consistently.
- Add `Support/ReportSummary` for processed/failed/ignored repositories.
- Update CLI output to show summary at the end.

### Phase 6 — Test Coverage

- Unit tests for:
  - Parser behavior with in-memory repository data.
  - ReportBuilder mapping.
  - Xlsx writer with small fixture report.
- Integration tests for Commands with a mocked Provider.

## 🔄 Backward Compatibility Strategy

- Keep `parserType`, `providerType`, and `writerType` unchanged.
- Keep file paths and output formatting intact.
- Keep CLI commands and names unchanged.

## ⚠️ Risks & Mitigations

- **Risk:** Output format drift during refactor.
  - **Mitigation:** Compare XLSX output against a known fixture.
- **Risk:** Wrapper becomes redundant.
  - **Mitigation:** Keep it minimal (validation + helpers only).
- **Risk:** DI changes cause runtime errors.
  - **Mitigation:** Update `services.yaml` incrementally and verify each phase.

## ✅ Exit Criteria

- Same CLI commands and configuration.
- Same XLSX output for a sample dataset.
- Improved unit test coverage for parsers and writer.
- Clear error messages for invalid configuration.

## 🧪 Suggested First Implementation Step

- Phase 1 only: add `Report` wrapper + validator and update Xlsx writer to consume it.
- This adds safety while keeping array-based flow intact.
