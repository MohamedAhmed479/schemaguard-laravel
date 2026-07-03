# Engineering Rules

Read this before editing code or tests. Skip it only for read-only inspection tasks where no change will be made.

## PHP / Package Conventions

- Every PHP file must include `declare(strict_types=1);`.
- PSR-4 root: `SchemaGuard\` -> `src/`.
- Classes should be `final` unless intentionally `abstract`.
- Use immutable value objects where appropriate.
- No fake returns, no placeholder behavior, and no comments pretending a feature works.
- No silent failure for destructive migration detection.
- No broad unrelated refactors during a scoped phase task.
- Preserve public contracts unless a roadmap requirement explicitly changes them.

## Product Correctness Rules

- The product is decision-oriented: `SAFE`, `WARNING`, `BLOCK`.
- Precision beats coverage.
- Proven high-confidence evidence can block.
- Ambiguity must warn rather than block.
- False negatives in destructive detection must never be silently hidden.
- False positives are dangerous and must be explicitly tested.
- Never execute host migrations or host models during analysis.

## Phase Discipline Rules

- Build top-to-bottom.
- Never begin Phase N+1 before the previous Definition of Done is green.
- Do not pull AST scanner work into Phase 2.
- Do not add `->change()` detection until Phase 3B.
- Do not build graph, policy, CLI pipeline, JSON output, Git diff, or raw SQL scanning prematurely.
- Preserve tested public contracts when upgrading internals.

## Git / Task Discipline

Before code changes:

```bash
git status
git diff --check
```

Before finishing:

```bash
git diff --check
vendor/bin/phpunit
```

Do not commit unless explicitly requested by the user.

If the worktree contains unrelated user changes, do not revert them. Work around them or ask only if they make the task impossible.
