# Context Update Protocol

Read this when completing any task. Skip it only for pure read-only reviews with no repository changes.

No task is complete until code, tests, and context agree.

## Before Work

1. Read minimum bootstrap context:
   - `context/00_START_HERE.md`
   - `context/03_CURRENT_STATE.md`
   - `context/06_ENGINEERING_RULES.md`
   - `context/09_ACTIVE_WORK.md`
2. Run Git inspection commands:

   ```bash
   git status
   git diff --check
   ```

3. Read only subsystem-specific files from `context/05_CODEBASE_MAP.md`.
4. Update `09_ACTIVE_WORK.md` only when the task scope is understood.

## During Work

1. Preserve scope boundaries.
2. Record new architectural decisions in `08_DECISION_LOG.md`.
3. Do not mark work complete before required tests run.
4. Keep planned features separate from implementation facts.
5. Avoid broad unrelated refactors.
6. Do not overwrite useful historical context without preserving the decision or outcome.

## After Work

1. Run required targeted tests and the full suite where relevant.
2. Update:
   - `context/03_CURRENT_STATE.md`
   - `context/09_ACTIVE_WORK.md`
   - `context/10_CHANGELOG.md`
3. Update `context/08_DECISION_LOG.md` only if a real decision was made.
4. Update `context/05_CODEBASE_MAP.md` only when files/modules materially changed.
5. Record exact test evidence, but summarize it compactly.
6. Keep "Implemented and verified", "Implemented — verification pending", "Planned — not implemented", "Historical acceptance evidence", and "Needs verification" distinct.
7. Run:

   ```bash
   git diff --check
   ```

8. Ensure context changes are included in the same task diff as code changes.

## Required Status Labels

Use only these labels for status statements:

- Implemented and verified
- Implemented — verification pending
- Planned — not implemented
- Historical acceptance evidence
- Needs verification

If a context file conflicts with source code, source code wins and the context must be updated.
