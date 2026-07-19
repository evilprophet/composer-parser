# AGENTS.md

Bootstrap and routing only. Durable shared rules live in local `./ai-rules`, which is an external read-only repository. This root file holds only routing plus project context. Keep it minimal.

## Shell Requirement

- On Windows, use WSL2 for all shell commands.
- On Linux/MacOS, use the native system shell.

## Rule Loading Order

Rules are layered from most to least specific. On conflict, the more specific file wins:

1. Subtree-local `AGENTS.md` for the files being edited, if present.
2. This root `AGENTS.md` - local routing and project context.
3. Matching language/task profiles in `./ai-rules/profiles/*/AGENTS.md`.
4. `./ai-rules/AGENTS.md` - global engineering baseline.

If `./ai-rules` is missing, ask the user for instructions and do not assume another rules source.

## Project Context

- This is a PHP 8.4+ Symfony Console application that builds one dependency report from multiple repositories.
- `README.md` documents operator workflow and runtime behavior.
- Keep `config/parameters.yaml.template` compact: show supported values, conditional requirements, and practical field details; keep broader explanations in `README.md`.
