# Active Work

Read this before taking ownership of a task and before handing work back. Do not skip it for implementation tasks.

Before taking ownership of a new task, update this file only after inspecting Git status and relevant context.

## Current Phase

Phase 6 preparation.

Status: Implemented and verified.

Phase 1, Phase 2, Phase 3, Phase 4, and Phase 5 are verified. Phase 6 is Planned - not implemented.

## Current Task

Phase 5 CLI acceptance audit and correction pass.

Status: Implemented and verified.

Phase 5 request, discovery, pipeline, reporter, JSON, progress, exit-code, and command integration have passed the targeted PHPUnit and Testbench gates listed in [03_CURRENT_STATE.md](03_CURRENT_STATE.md). The acceptance pass also corrected default config so example `enforce` symbols are not active by default.

No Phase 6 implementation has started.

## Current Working Tree State

Needs verification at the start of every new task.

Current working tree contains uncommitted Phase 5 source, tests, fixtures, and context changes. This task did not create a commit.

## Next Safe Task

Phase 6 - Robustness.

Entry gate:

- Re-run `git status`.
- Re-run `git diff --check`.
- Confirm Phase 1/2/3/4/5 tests are still green against the current working tree.
- Read [04_PHASE_PLAN.md](04_PHASE_PLAN.md), [05_CODEBASE_MAP.md](05_CODEBASE_MAP.md), and [07_TESTING_AND_COMMANDS.md](07_TESTING_AND_COMMANDS.md).

## Blocked By

No known project blocker.

Needs verification: current user intent and current Git state before beginning Phase 6.

## Do Not Start Yet

Planned - not implemented until prior gates are green:

- Raw SQL visitor and raw SQL scanning
- AST cache
- Golden-file E2E tests
- Hosted PR checks or SaaS/dashboard work

## Handoff Notes

- Keep Phase 1/2/3/4/5 behavior stable while preparing Phase 6.
- Do not add Raw SQL scanning, AST cache behavior, golden-file E2E gates, or hosted integrations unless Phase 6 or later is explicitly in scope.
- Keep documentation status labels truthful: implemented facts, planned work, and needs-verification items must stay separate.
