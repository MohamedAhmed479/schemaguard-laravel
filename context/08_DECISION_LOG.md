# Decision Log

Read this when changing architecture or phase boundaries. Skip it for mechanical test updates that do not introduce a new decision.

## ADR-001 - Phase 2 Uses PHP Tokens, Not Regex

- Decision: The Phase 2 migration parser uses `token_get_all(..., TOKEN_PARSE)` rather than hand-written regex over PHP source.
- Status: Implemented and verified.
- Why: Tokens avoid obvious false positives in strings/comments and keep the MVP safer without pulling Phase 3 AST scope forward.
- Consequences: Parser scope remains intentionally narrow until Phase 3.
- Evidence: `src/Migrations/MigrationParser.php`; `MigrationParserTest`.
- Revisit in: Phase 3B AST migration parser upgrade.

## ADR-002 - AST Migration Parsing Is Deferred

- Decision: AST migration parsing and `SchemaCallVisitor` are Phase 3 work.
- Status: Planned — not implemented.
- Why: The roadmap separates token extraction from full AST discovery and fluent-chain handling.
- Consequences: Phase 2 fixes must preserve tokenizer scope.
- Evidence: `IMPLEMENTATION_ROADMAP.md` Phase 3; no `src/Migrations/Visitors/SchemaCallVisitor.php` exists.
- Revisit in: Phase 3B.

## ADR-003 - Type Change Exists In Domain Model Before Detection

- Decision: `COLUMN_TYPE_CHANGED` remains in `ChangeType`, but `->change()` detection is deferred.
- Status: Implemented and verified.
- Why: Later phases need the stable event model while Phase 2 avoids unreliable fluent-chain parsing. Detection is Planned — not implemented.
- Consequences: Do not add `SchemaChangeEvent::columnTypeChanged()` or detection until Phase 3B unless the roadmap changes.
- Evidence: `src/ValueObjects/ChangeType.php`; no parser test expects type-change emission.
- Revisit in: Phase 3B.

## ADR-004 - Dynamic Destructive Arguments Become Indeterminate Events

- Decision: Dynamic destructive migration arguments produce indeterminate events instead of disappearing silently.
- Status: Implemented and verified.
- Why: Silent false negatives are dangerous for a deployment firewall.
- Consequences: Dynamic table/column names may warn later, but they must be visible to downstream phases.
- Evidence: `SchemaChangeEvent::indeterminate()`; dynamic parser fixtures/tests.
- Revisit in: Phase 4 policy mapping.

## ADR-005 - `down()` Operations Are Excluded

- Decision: The Phase 2 parser emits destructive events only from `up()` migration methods.
- Status: Implemented and verified.
- Why: Rollback logic must not be treated as forward-deployment destructive behavior.
- Consequences: Parser scope tracking must remain covered by regression tests.
- Evidence: `test_it_ignores_destructive_calls_in_down_method`.
- Revisit in: Phase 3B AST scope upgrade.

## ADR-006 - Parser Failures Degrade Safely With Accessible Diagnostics

- Decision: Missing/malformed migration files return safe empty results and record public diagnostics.
- Status: Implemented and verified.
- Why: One bad file should not crash a full analysis run.
- Consequences: Consumers can inspect `MigrationParser::diagnostics()` after parsing.
- Evidence: `MigrationParser::diagnostics()`; malformed and missing migration tests.
- Revisit in: Phase 5 pipeline reporting.

## ADR-007 - Phase 1 Command Is A Smoke-Test Scaffold

- Decision: `schemaguard:check` currently registers and prints a Deployment Firewall banner, but performs no analysis.
- Status: Implemented and verified.
- Why: Phase 1 proves package wiring before the pipeline exists.
- Consequences: Do not add CLI options or analysis behavior until Phase 5.
- Evidence: `src/Console/Commands/CheckCommand.php`; `CheckCommandTest`; Testbench command output.
- Revisit in: Phase 5.

## ADR-008 - Package Behavior Is Test-Gated Phase By Phase

- Decision: Each phase must keep prior phase tests green before moving forward.
- Status: Implemented and verified.
- Why: SchemaGuard is risk-sensitive; regressions in extraction or command wiring undermine later decisions.
- Consequences: Phase 3 work must run Phase 2 parser regression tests.
- Evidence: `IMPLEMENTATION_ROADMAP.md`; current PHPUnit suite.
- Revisit in: Every phase transition.
