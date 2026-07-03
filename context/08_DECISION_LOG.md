# Decision Log

Read this when changing architecture or phase boundaries. Skip it for mechanical test updates that do not introduce a new decision.

## ADR-001 - Phase 2 Used PHP Tokens, Not Regex

- Decision: The Phase 2 migration parser used `token_get_all(..., TOKEN_PARSE)` rather than hand-written regex over PHP source.
- Status: Historical acceptance evidence.
- Why: Tokens avoided obvious false positives in strings/comments without pulling Phase 3 AST scope forward.
- Consequences: Phase 3 later replaced this internal parser while preserving public contracts.
- Evidence: Phase 2 acceptance tests and roadmap Phase 2.
- Revisit in: Not applicable unless investigating Phase 2 history.

## ADR-002 - AST Migration Parsing Replaced The Token Parser In Phase 3

- Decision: Phase 3B replaced the token parser internals with PHP-Parser and `SchemaCallVisitor`.
- Status: Implemented and verified.
- Why: AST parsing reliably scopes `up()` vs `down()`, custom Blueprint variables, table context, and fluent `->change()` chains.
- Consequences: `MigrationParser::parseMany()` and `parseFile()` remain stable while internals are AST-backed.
- Evidence: `src/Migrations/MigrationParser.php`; `src/Migrations/Visitors/SchemaCallVisitor.php`; `MigrationParserTest`.
- Revisit in: Phase 6 parser robustness work.

## ADR-003 - Type Change Exists In The Domain Model And Is Detected By AST

- Decision: `COLUMN_TYPE_CHANGED` remains in `ChangeType`, and Phase 3B detects `->change()` through AST method-call chains.
- Status: Implemented and verified.
- Why: Type changes need a stable event model and AST-safe fluent-chain detection.
- Consequences: Type-change detection must remain covered by parser regression tests.
- Evidence: `src/ValueObjects/ChangeType.php`; `SchemaChangeEvent::columnTypeChanged()`; `test_it_parses_column_type_changes_with_change_modifier`.
- Revisit in: Phase 4 policy mapping.

## ADR-004 - Dynamic Destructive Arguments Become Indeterminate Events

- Decision: Dynamic destructive migration arguments produce indeterminate events instead of disappearing silently.
- Status: Implemented and verified.
- Why: Silent false negatives are dangerous for a deployment firewall.
- Consequences: Dynamic table/column names may warn later, but they must be visible to downstream phases.
- Evidence: `SchemaChangeEvent::indeterminate()`; dynamic parser fixtures/tests.
- Revisit in: Phase 4 policy mapping.

## ADR-005 - `down()` Operations Are Excluded

- Decision: The migration parser emits destructive events only from `up()` migration methods.
- Status: Implemented and verified.
- Why: Rollback logic must not be treated as forward-deployment destructive behavior.
- Consequences: Parser scope tracking must remain covered by regression tests.
- Evidence: `test_it_ignores_destructive_calls_in_down_method`.
- Revisit in: Phase 6 parser robustness work.

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
- Why: SchemaGuard is risk-sensitive; regressions in extraction or scanning undermine later decisions.
- Consequences: Phase 4 work must run Phase 1/2/3 regression tests.
- Evidence: `IMPLEMENTATION_ROADMAP.md`; current PHPUnit suite.
- Revisit in: Every phase transition.

## ADR-009 - Usage Scanner Is Target-Scoped, Not Generic String Search

- Decision: Phase 3 scanning derives a `SymbolTargetSet` from schema events and visitors only emit usage for those targets.
- Status: Implemented and verified.
- Why: Generic string search would create dangerous false positives from array keys, local variables, translation keys, or unrelated strings.
- Consequences: New visitors must prove semantic context and pass the false-positive fixture gate.
- Evidence: `src/ValueObjects/SymbolTargetSet.php`; `StaticAnalysisScannerTest::test_scanner_only_emits_symbols_from_schema_change_targets`; `StaticAnalysisScannerTest::test_false_positive_fixture_yields_zero_usages_for_target_column`.
- Revisit in: Future visitor additions.

## ADR-010 - Raw SQL Matching Helper Exists Without A Raw SQL Visitor

- Decision: `ColumnTokenMatcher::matchesInSql()` is implemented and tested, but no Raw SQL visitor is added in Phase 3.
- Status: Implemented and verified.
- Why: The roadmap requires the matcher now while Raw SQL scanning remains later-phase work.
- Consequences: Do not wire raw SQL scanning into Phase 3 or Phase 4 unless the roadmap scope changes.
- Evidence: `src/Scanning/ColumnTokenMatcher.php`; no `RawSqlVisitor` exists.
- Revisit in: Phase 6.

## ADR-011 - Graph Edges Reject Unknown Nodes

- Decision: `DependencyGraph::addEdge()` throws on unknown source or target nodes instead of silently creating partial nodes.
- Status: Implemented and verified.
- Why: Silent graph corruption would make impact paths and policy exposure checks untrustworthy.
- Consequences: Builders must add every node explicitly before adding edges.
- Evidence: `src/Graph/DependencyGraph.php`; `DependencyGraphTest::test_unknown_edge_endpoint_throws_clear_exception`.
- Revisit in: Only if a future graph import format needs validated bulk loading.

## ADR-012 - Run Metadata Stays Outside Policy Results

- Decision: Phase 5 reports migration/source/unparsed counts through `AnalysisRunResult` and `AnalysisMetadata`, not by adding CLI metadata to `PolicyResult`.
- Status: Implemented and verified.
- Why: `PolicyResult` should remain the deterministic policy-domain verdict, while CLI/JSON reporting needs run-level metadata.
- Consequences: Reporters consume `AnalysisRunResult`; policy tests remain focused on findings and severity.
- Evidence: `src/Pipeline/AnalysisRunResult.php`; `src/Pipeline/AnalysisMetadata.php`; `ConsoleReporterTest`.
- Revisit in: Only if a later public API explicitly needs a single serialized result object.
