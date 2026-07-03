# SchemaGuard — `IMPLEMENTATION_ROADMAP.md`

> **Incremental Build Guide — Phase 1 Product (`schemaguard/laravel`)**
> Companion to `TECHNICAL_BLUEPRINT.md`. The blueprint is the *what* (architecture, class specs, algorithms). This roadmap is the *order* (what to build, in what sequence, and how to prove each step works).
> Audience: an autonomous AI Coding Agent. Owner perspective: Senior TPM + Lead Backend Architect.

---

## How To Use This Document

1. **Build strictly top-to-bottom.** Each phase depends on the previous one's *Definition of Done* being met. Do **not** start Phase N+1 until Phase N's DoD is green.
2. **Test-gated phases.** Every phase ends with a runnable test command. A phase is "done" only when that command passes — not when the code "looks complete."
3. **Cross-reference the blueprint.** Every class, namespace, value object, and rule named here is fully specified in `TECHNICAL_BLUEPRINT.md`. Section references like *(BP §3.5)* point there. When this roadmap and the blueprint agree, follow them; the blueprint holds the micro-detail.
4. **No placeholders, no faked returns.** If a capability is out of a phase's scope, it is absent until its phase arrives — never stubbed with a lie.
5. **`declare(strict_types=1);` at the top of every PHP file. PSR-4 under `SchemaGuard\` → `src/`. Every class `final` unless `abstract`.**

### Standing assumptions (the source-of-truth logic from the strategy)

- The product issues a **decision, not a report**: every run resolves to `SAFE`, `WARNING`, or `BLOCK`.
- **Precision beats coverage.** A change blocks a merge only on *high-confidence, provable* evidence. Ambiguity → `WARNING`. This is the explicit defense against the "developers hate false positives" risk.
- The four Phase-1 destructive events are: `COLUMN_DROPPED`, `COLUMN_RENAMED`, `TABLE_DROPPED`, `COLUMN_TYPE_CHANGED`.
- **Start limited, evolve to AST.** Early phases may use lighter parsing; the moat (AST + dependency graph + rules) matures across phases. The codebase scanner is AST from day one — regex is rejected there because it cannot tell a real column reference from a coincidental string.

### Phase overview & dependency chain

| Phase | Name | Builds | Gating DoD |
|---|---|---|---|
| 1 | Foundation & Scaffolding | composer, provider, config, `schemaguard:check` skeleton | Command runs, prints banner, exit 0; empty suite green |
| 2 | Extraction Engine | `MigrationDiscovery`, `MigrationParser`, event value objects | Parser emits exact `SchemaChangeEvent[]` on fixtures |
| 3 | Discovery Engine (AST) | PHP-Parser, `CodebaseIndexer`, usage visitors, type resolver | Scanner finds usages at correct `Confidence`; FP fixture → 0 usages |
| 4 | Logic Engine | `DependencyGraph(+Builder)`, `PolicyEngine`, `PolicyConfiguration` | Full decision matrix + overrides verified; impact paths built |
| 5 | Presentation & CLI | `ConsoleReporter`, `ExitCodeResolver`, `AnalysisPipeline`, JSON | E2E run renders banner + correct exit code |
| 6 | Edge Cases & Robustness | `RawSqlVisitor`, complex relations, full feature suite | All edge-case tests green; golden-file E2E passes |

```
P1 ──▶ P2 ──▶ P3 ──▶ P4 ──▶ P5 ──▶ P6
 │      │      │      │      │      │
 └ scaffold   └ events     └ graph+policy   └ wire+render   └ harden
        └ value objects    └ usages+confidence            └ regression suite
```

---

## Phase 1: Foundation & Package Scaffolding

### 1. Objective
Stand up an installable, auto-discovered Laravel package whose `schemaguard:check` command runs end-to-end with **zero analysis logic** — it prints a banner and returns exit code `0`. This establishes the skeleton, the test harness (Orchestra Testbench), and the configuration plumbing that every later phase plugs into.

### 2. Tasks

- [ ] **`composer.json`** — declare the package exactly as in *(BP §2)*:
  - `name`: `schemaguard/laravel`; `license`: `MIT`.
  - `require`: `php ^8.2`, `illuminate/console ^11.0||^12.0`, `illuminate/support ^11.0||^12.0`, `illuminate/filesystem ^11.0||^12.0`, `nikic/php-parser ^5.0`.
  - `require-dev`: `orchestra/testbench ^9.0||^10.0`, `phpunit/phpunit ^11.0`.
  - `autoload.psr-4`: `{ "SchemaGuard\\": "src/" }`; `autoload-dev.psr-4`: `{ "SchemaGuard\\Tests\\": "tests/" }`.
  - `extra.laravel.providers`: `["SchemaGuard\\SchemaGuardServiceProvider"]` (package auto-discovery — no manual registration in host apps).
  - Add a `scripts.test` entry: `"test": "phpunit"`.
- [ ] **`config/schemaguard.php`** — create the file with the **full commented template** from *(BP §6)*. It is fine for later phases to *consume* these keys; Phase 1 only needs the file to exist and be publishable. At minimum populate `scan_paths`, `migration_paths`, `ignore_paths`, `policy.modes`, `exit_codes`. (Do not trim the template — downstream phases reference these keys by name.)
- [ ] **`src/SchemaGuardServiceProvider.php`** *(BP §3.1)* — extends `Illuminate\Support\ServiceProvider`:
  - `register()`: `mergeConfigFrom(__DIR__.'/../config/schemaguard.php', 'schemaguard')` so config resolves even before publishing.
  - `boot()`: guarded by `runningInConsole()`; `publishes([...], 'schemaguard-config')` and `commands([CheckCommand::class])`.
- [ ] **`src/Console/Commands/CheckCommand.php`** *(BP §3.2)* — extends `Illuminate\Console\Command`:
  - `$signature = 'schemaguard:check'` (Phase 1: **no options yet** — options arrive in Phase 5).
  - `$description` = the firewall one-liner.
  - `handle(): int` — print a banner via `$this->line()` / `$this->info()` (e.g. `SchemaGuard — Deployment Firewall for Database Changes` and `No analysis wired yet.`) and `return self::SUCCESS;` (0). **No parsing, no scanning.**
- [ ] **`src/Exceptions/SchemaGuardException.php`** — abstract-or-base exception that all package exceptions will extend; create now so later phases extend a stable root.
- [ ] **`tests/TestCase.php`** — extends `Orchestra\Testbench\TestCase`; override `getPackageProviders($app)` to return `[SchemaGuardServiceProvider::class]`. This boots a minimal Laravel app around the package for every test.
- [ ] **`tests/Feature/CheckCommandTest.php`** — one smoke test:
  - `test_command_is_registered_and_runs_successfully()` using Testbench's pending-command assertions:
    ```php
    $this->artisan('schemaguard:check')
         ->expectsOutputToContain('Deployment Firewall')
         ->assertExitCode(0);
    ```
- [ ] **`phpunit.xml.dist`** — define `Unit` and `Feature` test suites pointing at `tests/Unit` and `tests/Feature`; bootstrap `vendor/autoload.php`.
- [ ] **`.gitattributes`** — `export-ignore` for `tests/`, `phpunit.xml.dist`, `.gitattributes` (keep the published Composer tarball lean).
- [ ] **`README.md`** + **`LICENSE.md`** — minimal README (install + the single command) and the MIT license text. The open-source-trust go-to-market depends on a credible README, but a full one can wait until Phase 6.

### 3. Internal Dependencies
None. This is the root phase. (External: a working PHP 8.2+ toolchain and Composer.)

### 4. Definition of Done
- `composer install` completes with no errors and the provider is auto-discovered (visible under `php artisan package:discover` output in a Testbench/host context).
- Running the command prints the banner and returns exit code `0`.
- The single smoke test passes; the test suite is green (1 passing test, 0 failures).
- `vendor/bin/testbench vendor:publish --tag=schemaguard-config` copies `config/schemaguard.php` into the skeleton app's `config/`.

### 5. Testing Instruction
```bash
composer install
vendor/bin/phpunit tests/Feature/CheckCommandTest.php      # smoke test must pass
vendor/bin/testbench schemaguard:check                      # manual: prints banner, echo $? == 0
```
> `vendor/bin/testbench` is the Testbench binary that runs Artisan against the package's skeleton app — the canonical way to manually exercise a package command without a full host app.

---

## Phase 2: The Extraction Engine (Migration Parser)

### 2. Objective
Turn migration **files** into a typed collection of `SchemaChangeEvent` objects for the three structurally-simple destructive operations: `dropColumn`, `renameColumn`, `dropTable`/`dropIfExists`. Output is a `SchemaChangeEvent[]` that Phase 4 will later evaluate. (Type-change detection is deferred to Phase 3, where the AST makes the `->change()` fluent-chain reliably detectable — see the architectural note below.)

> ### ⚠ Architectural Decision — "regex or basic tokens" (read before coding)
> The strategy is explicit that **regex is the weak surface version that errs often**, and that the moat is AST. For the *constrained* migration-parsing problem, a lightweight approach is an acceptable MVP, but **use PHP's native tokenizer `token_get_all()`, not hand-rolled regex.** Tokens respect string literals, comments, and whitespace; regex over PHP source is brittle and will misfire on edge formatting.
> **Known limitations of the Phase-2 token MVP** (documented, not hidden): it handles well-formatted single-statement calls; it does **not** robustly scope `up()` vs `down()`, track custom `Blueprint` variable names, handle multi-table closures, or detect `->change()` chains. **Phase 3 introduces `nikic/php-parser`, and the production target is the AST-based `MigrationParser` + `SchemaCallVisitor` specified in BP §3.5/§4.1**, which resolves every one of these limitations. The agent may either (a) ship the token MVP now and swap in the AST parser during Phase 3, or (b) jump straight to the AST parser if Phase 3's PHP-Parser dependency is pulled forward. Either way, **the public contract — `MigrationParser::parseMany(array $paths): array` returning `SchemaChangeEvent[]` — stays identical**, so no downstream code changes when the internals upgrade.

### 2. Tasks

- [ ] **Value objects** *(BP Appendix A)* — create as `final readonly` under `src/ValueObjects/`:
  - `ChangeType.php` (enum: `COLUMN_DROPPED`, `COLUMN_RENAMED`, `TABLE_DROPPED`, `COLUMN_TYPE_CHANGED`).
  - `TableReference.php` (`string $table`, `id(): string`).
  - `ColumnReference.php` (`string $table`, `string $column`, `id()`, `equals()`).
  - `SourceLocation.php` (`string $file`, `int $line`, `?int $column`).
  - `SchemaChangeEvent.php` with named constructors `columnDropped()`, `columnRenamed()`, `tableDropped()`, and `indeterminate()` (for dynamic args). Include the `?string $renamedTo`, `?string $newType`, and `bool $indeterminate` fields now even though type-change is wired in Phase 3.
- [ ] **`src/Migrations/MigrationDiscovery.php`** *(BP §3.4)* — inject `Illuminate\Filesystem\Filesystem` + config:
  - `resolve(AnalysisRequest|array $opts): array` — Phase 2 needs only the **`EXPLICIT`** path (`--migrations=` style: an array of file paths) and the **`PENDING`** path (glob `config('schemaguard.migration_paths')` for `*.php`). The `GIT_DIFF` strategy can stub to throw `not-yet-supported` until Phase 5 (it is *absent*, not faked).
  - Returns absolute paths sorted ascending by filename.
- [ ] **`src/Migrations/MigrationParser.php`** *(BP §3.5)* — public API:
  - `parseMany(array $paths): array` → merges `parseFile()` over all paths.
  - `parseFile(string $path): array` → reads the file, extracts events, returns `SchemaChangeEvent[]`. **Must not throw on a malformed file** — catch, return `[]`, and record a diagnostic (degrade, never abort the run).
  - Internal extraction logic (token MVP): stream `token_get_all($source)`; track the most recent `Schema::table('…')`/`create('…')` string literal as the current table; on encountering a `dropColumn`/`renameColumn`/`drop`/`dropIfExists` identifier followed by its string argument(s), emit the corresponding event with `SourceLocation` (the token line). Handle `dropColumn(['a','b'])` → multiple `COLUMN_DROPPED` events. A non-literal (variable/concatenation) argument → `SchemaChangeEvent::indeterminate(...)` with a reason, **never a silent skip** (false negatives are the most dangerous failure).
- [ ] **`src/Exceptions/MigrationParseException.php`** — thrown internally and caught/degraded; extends `SchemaGuardException`.
- [ ] **Test fixtures** under `tests/Fixtures/migrations/` (parsed, never executed):
  - `2024_06_01_000000_drop_phone_from_users.php` — anonymous class, `up()` drops `phone`, `down()` re-adds it.
  - `2024_06_02_000000_rename_users_column.php` — `renameColumn('full_name', 'name')`.
  - `2024_06_03_000000_drop_legacy_logs_table.php` — `Schema::dropIfExists('legacy_logs')`.
  - `2024_06_05_000000_drop_multi_columns.php` — `dropColumn(['street','zip'])`.
  - `2024_06_06_000000_dynamic_drop.php` — `dropColumn($columnName)` (dynamic).
- [ ] **`tests/Unit/Migrations/MigrationParserTest.php`** and **`MigrationDiscoveryTest.php`**.

### 3. Internal Dependencies
- **Phase 1 complete** (provider, config, test harness).
- Value objects must exist before the parser (the parser returns them).

### 4. Definition of Done
- Parsing `drop_phone_from_users` yields **exactly one** `COLUMN_DROPPED` event for `users.phone`, and **zero** events from its `down()` (token MVP note: acceptable if down-scoping is approximate, but the fixture must pass — keep the `down()` body trivial enough that the MVP doesn't misfire, or implement minimal `function up` scoping).
- `renameColumn('full_name','name')` → one `COLUMN_RENAMED` with `column=users.full_name`, `renamedTo=name`.
- `dropIfExists('legacy_logs')` → one `TABLE_DROPPED` for `legacy_logs`.
- `dropColumn(['street','zip'])` → **two** `COLUMN_DROPPED` events.
- `dropColumn($columnName)` → one `indeterminate` event (not a crash, not silence).
- `MigrationDiscovery` returns the fixture paths in sorted order for both EXPLICIT and PENDING strategies.

### 5. Testing Instruction
```bash
vendor/bin/phpunit --filter MigrationParserTest
vendor/bin/phpunit --filter MigrationDiscoveryTest
```
Targeted assertion to include verbatim:
```php
$events = $parser->parseFile($fixtures.'/2024_06_01_000000_drop_phone_from_users.php');
$this->assertCount(1, $events);
$this->assertSame(ChangeType::COLUMN_DROPPED, $events[0]->type);
$this->assertSame('users', $events[0]->column->table);
$this->assertSame('phone', $events[0]->column->column);
```

---

## Phase 3: The Discovery Engine (AST Code Scanner)

### 3. Objective
Integrate `nikic/php-parser` and build the **AST scanning core**: parse the application once into ASTs, then traverse them to find where the columns/tables from Phase 2 are actually *used*, attaching a `Confidence` to every match. Focus surfaces for this phase: **Eloquent Models, Controllers, and API Resources**. This is the moat — and the phase where false-positive suppression (the confidence model) is implemented. Also upgrade the migration parser to AST and add `COLUMN_TYPE_CHANGED` detection here.

### 3. Tasks

- [ ] **Value objects / enums** *(BP Appendix A)*:
  - `Confidence.php` (enum int-backed: `LOW=1`, `MEDIUM=2`, `HIGH=3`, `DEFINITIVE=4`, with `atLeast()`).
  - `SurfaceType.php` (enum: `MODEL_SCHEMA`, `ELOQUENT_QUERY`, `API_RESOURCE`, `CONTROLLER`, `RELATION`, `RAW_SQL`).
  - `Usage.php` (`symbol`, `surface`, `confidence`, `location`, `detail`).
- [ ] **`src/Scanning/ParsedFile.php`** — value object: `path`, `?array $ast`, `bool $parsed`, `?string $error`; named ctors `parsed()` / `failed()`.
- [ ] **`src/Scanning/CodebaseIndexer.php`** *(BP §3.6)* — inject `Filesystem` + config (+ optional cache):
  - Build the parser via `(new \PhpParser\ParserFactory)->createForNewestSupportedVersion()`.
  - `index(array $scanPaths): array` — recursively glob `*.php`, skip files matching `ignore_paths` (`fnmatch` against absolute path), and parse each **once**, decorating every AST with `NameResolver` + `ParentConnectingVisitor` so visitors share FQ-name and parent-link work. Return `array<string,ParsedFile>` keyed by path. Parse failures → `ParsedFile::failed()`, never fatal.
- [ ] **Upgrade `MigrationParser` to AST** *(BP §3.5)* — replace the token MVP internals with the AST flow: parse → `NameResolver` + `ParentConnectingVisitor` → `SchemaCallVisitor`. Add **`src/Migrations/Visitors/SchemaCallVisitor.php`** implementing the full grammar from BP §4.1, including:
  - `up()`-scope guard (`insideUp`), table-context tracking, **custom `Blueprint` variable resolution** from the closure parameter, multi-table reset on closure exit.
  - **`COLUMN_TYPE_CHANGED` detection** via `chainHasChangeModifier()` — walk parent links outward from a column-type method call (`string`/`integer`/…) until a terminating `->change()` is found. Emit `columnTypeChanged(column, newType)`.
  - Public contract (`parseMany`/`parseFile`) is unchanged → Phase 2 tests still pass.
- [ ] **`src/Scanning/LocalTypeResolver.php`** *(BP §3.8)* — intra-method variable→class inference. Implement, in priority order: parameter type hints → `@var` docblocks → `new X()` → static model entrypoints (`X::find/query/where/create/first/firstOrFail`) → relationship traversal → `DB::table('t')` literal binding. Unresolved → `ResolvedType::unknown()`. **Scope is intentionally intra-procedural** — document the non-goals (no cross-method/container/interface flow).
- [ ] **`src/Scanning/ColumnTokenMatcher.php`** *(BP §3.9)* — the rarity heuristic + SQL-boundary matcher:
  - `rarity(string $column): Rarity` using `config('schemaguard.common_column_names')`.
  - `confidenceForUnresolved(string $column): Confidence` (RARE/MODERATE → MEDIUM, COMMON → LOW).
  - `matchesInSql(string $sql, string $token): bool` with non-identifier boundary lookarounds (used by Phase 6, but implement and unit-test now).
- [ ] **`src/Scanning/ModelTableMap.php`** — maps model FQCN → table name; built by ingesting model files. Table resolution must replicate Eloquent: explicit `$table` else `Str::snake(Str::pluralStudly(class_basename))`.
- [ ] **`src/Scanning/Visitors/AbstractUsageVisitor.php`** — base extending `PhpParser\NodeVisitorAbstract`: holds the current `ParsedFile`, the `SymbolTargetSet`, accumulated `Usage[]`, a `reset()` method, FQ-name/parent helpers, and a protected `emit(Usage)`.
- [ ] **Usage visitors** *(BP §3.7.1–3.7.4)* — this phase's three focus surfaces:
  - **`EloquentModelVisitor.php`** — registration mode (FQCN + table) and usage mode. Detect column-bearing positions: `$fillable`/`$guarded`/`$casts` keys/`$appends`/`$hidden`/`$visible`/`$dates` (→ `DEFINITIVE`), legacy `get*Attribute`/`set*Attribute` (→ `DEFINITIVE`), modern `Attribute`-return accessors (→ `HIGH`, corroborated per BP §5.1), relationship FK args (→ `RELATION`), and local `scope*` bodies (sub-walk).
  - **`ApiResourceVisitor.php`** — classes extending `JsonResource`/`ResourceCollection`; `$this->col` and `'k' => $this->col` inside `toArray()` (→ `DEFINITIVE` when the associated model maps to a target table; `HIGH` on naming-miss fallback).
  - **`ControllerVisitor.php`** — validation rule keys (`$request->validate([...])`, FormRequest `rules()`) → `HIGH`; `$request->input('col')`/`$request->col` → `MEDIUM`; Eloquent queries → delegate to `EloquentUsageVisitor`.
  - **`EloquentUsageVisitor.php`** — `$x->col` property fetch (resolve `$x` via `LocalTypeResolver`) and query-builder string columns (`where/select/orderBy/...` per `config('schemaguard.builder_column_methods')`), resolving the receiver root. Apply the **confidence tiers** from BP §4.2: resolved binding → `DEFINITIVE`/`HIGH`; unresolved → `ColumnTokenMatcher` MEDIUM/LOW; bare array keys / `trans()`/`route()`/`view()` strings / unrelated locals → **REJECT**.
- [ ] **`src/Scanning/StaticAnalysisScanner.php`** *(BP §3.7)* — the coordinator. **Two-pass**: PASS 1 ingests all models into `ModelTableMap`; PASS 2 runs every visitor over every parsed file. `dedupe()` collapses identical `(symbol, location)` pairs keeping max confidence. Returns `Usage[]`.
- [ ] **`SymbolTargetSet`** (value object) — `fromEvents(array $events)` → distinct target tables + `table.column` pairs to hunt for.
- [ ] **Fixtures** under `tests/Fixtures/Models|Http`:
  - `Models/User.php` — `$fillable` incl. `phone`, `$casts`, a legacy accessor, a modern `Attribute` accessor that is *computed* (no backing column), a `posts()` relation, a `scopeActive()`.
  - `Http/Resources/UserResource.php` — exposes `$this->phone`.
  - `Http/Controllers/UserController.php` — validates `phone`, runs `User::where('phone', …)`.
  - `Fixtures/false_positive.php` — a plain file with `['phone' => $input]` array key and a local `$phone` var (must yield **zero** usages).
- [ ] **Unit tests**: `EloquentModelVisitorTest`, `EloquentUsageVisitorTest`, `ApiResourceVisitorTest`, `LocalTypeResolverTest`, `ColumnTokenMatcherTest`.

### 3. Internal Dependencies
- **Phase 2 complete** (events + value objects; the scanner consumes `SchemaChangeEvent[]` to know what to look for).
- `nikic/php-parser` installed (already in `composer.json` from Phase 1).
- `ModelTableMap` must be built (PASS 1) before query/resource/controller visitors run (PASS 2) — enforce in `StaticAnalysisScanner`.

### 4. Definition of Done
- `EloquentModelVisitor` finds `users.phone` in `$fillable` at `Confidence::DEFINITIVE`; finds the legacy accessor's column; assigns the *computed* modern accessor **no** column usage (per BP §5.1).
- `EloquentUsageVisitor`: `User::where('phone', …)` → `DEFINITIVE`; `$row->phone` with unresolved `$row` → `MEDIUM` (rare) or `LOW` (common); the `false_positive.php` fixture → **0 usages** (the central FP test).
- `ApiResourceVisitor`: `$this->phone` in `UserResource::toArray()` → `API_RESOURCE`/`DEFINITIVE`.
- `LocalTypeResolver` resolves a `User $user` param, a `new User`, and `User::find()` to the `User` model; resolves unrelated variables to `unknown`.
- `MigrationParser` (now AST) still passes all Phase 2 tests **and** newly emits `COLUMN_TYPE_CHANGED` for `$table->string('email')->change()`.
- `CodebaseIndexer` parses the fixture app and degrades a deliberately-broken fixture to `ParsedFile::failed()` without aborting.

### 5. Testing Instruction
```bash
vendor/bin/phpunit --testsuite Unit            # all Phase 2 + Phase 3 unit tests
vendor/bin/phpunit --filter EloquentUsageVisitorTest
vendor/bin/phpunit --filter MigrationParserTest   # regression: Phase 2 still green after AST swap
```
Non-negotiable FP assertion to include:
```php
// Scanning a plain ['phone' => $x] array and a local $phone must NOT register a column usage.
$usages = $this->scanFixture('false_positive.php', target: 'users.phone');
$this->assertCount(0, $usages, 'Coincidental string must not be treated as a column usage');
```

---

## Phase 4: The Logic Engine (Impact Analysis & Graph)

### 4. Objective
Connect a changed database column to its code usages via a **Dependency Graph**, and implement the deterministic **Policy Engine** that turns `(event × usage-confidence × config)` into a `SAFE`/`WARNING`/`BLOCK` verdict — using the exact rules from the strategy (drop of a used column = BLOCK; type change of a used column = WARNING; additive = SAFE).

### 4. Tasks

- [ ] **`src/ValueObjects/Severity.php`** (enum int-backed: `SAFE=0`, `WARNING=1`, `BLOCK=2`; ordered for `max()` aggregation).
- [ ] **`src/Graph/GraphNode.php`** — `id`, `NodeType $type` (Column/Table/Model/Resource/ControllerAction/Route), `label`, `?SourceLocation`. Stable ID scheme from BP §3.10 (`column:users.phone`, `model:App\Models\User`, `route:GET:/api/users/{user}`, …).
- [ ] **`src/Graph/ImpactPath.php`** — an ordered chain of `GraphNode`s with a `__toString()` rendering `a → b → c`.
- [ ] **`src/Graph/DependencyGraph.php`** *(BP §3.10)* — adjacency list: `addNode()`, `addEdge()` (dedup), `reachableFrom(id): GraphNode[]` (iterative BFS), `exposedPaths(columnId): ImpactPath[]` (DFS to sinks of type `Route`/`Resource`), `reachesExposedSurface(columnId): bool`.
- [ ] **`src/Scanning/Visitors/RouteVisitor.php`** *(BP §3.7.5)* — scan `routes/*.php` for `Route::get/post/put/patch/delete/apiResource/resource` → `RouteBinding[]` (verb, uri, controllerFqcn, method). Emits **no `Usage`** — feeds the graph. (Needed here because the graph's exposed-surface endpoints come from routes.)
- [ ] **`src/Graph/DependencyGraphBuilder.php`** *(BP §3.11)* — `build(index, usages, routeBindings): DependencyGraph`. Edges: `column→table`, `model→table`, `column→model`, `resource→model`, `column→resource`, `action→controller`, `controller→model`, `route→action`. Produce per-column `ImpactPath[]` and the `reachesExposedSurface` flag.
- [ ] **`src/Policy/PolicyConfiguration.php`** *(BP §3.12.3)* — typed wrapper around `config('schemaguard')`. `fromArray(array): self`, validating enum-like values and throwing `ConfigurationException` on unknown modes/severities. Expose: `isIgnored(path)`, ignored/enforced symbol checks, per-type `mode()`, `customRules()`, `escalateExposedToBlock()`, `blockConfidenceFloor()`, `treatWarningsAsFailure()`, `warningExitCode()`. Bind as a singleton in the provider (`register()`).
- [ ] **`src/Policy/EventFinding.php`** — `readonly` (`SchemaChangeEvent $event`, `Usage[] $usages`, `Severity $severity`, `ImpactPath[] $paths`).
- [ ] **`src/Policy/PolicyResult.php`** *(BP §3.12.4)* — `readonly` (`EventFinding[] $findings`, `Severity $overall` = max across findings, `int $blockCount/$warningCount/$safeCount`, `string[] $diagnostics`); `static empty(): self` (no events → SAFE).
- [ ] **`src/Policy/PolicyEngine.php`** *(BP §3.12)* — `evaluate(events, usages, graph): PolicyResult`. For each event: gather relevant usages, compute `peakConfidence`, read `reachesExposedSurface`, apply the **decision matrix** (BP §3.12.1), then `PolicyConfiguration::applyOverrides()` in precedence order: **ignore → enforce → per-type mode clamp → custom rule**. Optional exposure escalation only when `escalate_exposed_to_block` is true. Aggregate to `PolicyResult`.
- [ ] **`src/Exceptions/ConfigurationException.php`**.
- [ ] **Unit tests**: `DependencyGraphTest`, `DependencyGraphBuilderTest`, `PolicyEngineTest`, `PolicyConfigurationTest`.

#### Decision matrix (must be encoded exactly — BP §3.12.1)
| `ChangeType` | DEFINITIVE/HIGH usage | MEDIUM/LOW usage | No usage |
|---|---|---|---|
| `COLUMN_DROPPED` | **BLOCK** | WARNING | SAFE |
| `COLUMN_RENAMED` | **BLOCK** | WARNING | SAFE |
| `TABLE_DROPPED` | **BLOCK** | WARNING | SAFE |
| `COLUMN_TYPE_CHANGED` | **WARNING** | WARNING | SAFE |

### 3. Internal Dependencies
- **Phase 3 complete** (`Usage[]` with confidence; `ModelTableMap`).
- `Severity` enum before `PolicyEngine`.
- `RouteVisitor` + `DependencyGraph` before `DependencyGraphBuilder`.
- `PolicyConfiguration` before `PolicyEngine` (engine reads overrides from it).

### 4. Definition of Done
- **Matrix proven exhaustively**: `COLUMN_DROPPED` + a `DEFINITIVE` usage → `BLOCK`; + only a `MEDIUM` usage → `WARNING`; + no usage → `SAFE`. `COLUMN_TYPE_CHANGED` + `DEFINITIVE` → `WARNING` (never BLOCK by default). All 12 cells covered by tests.
- **Overrides proven**: an `enforce.columns` entry forces `BLOCK` even with zero usages; an `ignore.columns` entry forces `SAFE` even with a `DEFINITIVE` usage; `policy.modes.column_dropped = 'warn'` downgrades a would-be BLOCK to WARNING; a matching `custom_rules` entry wins over all of the above.
- **Graph proven**: for `users.phone` used by `User` → `UserController@show` routed at `GET /api/users/{user}`, `exposedPaths()` returns an `ImpactPath` whose string equals `users.phone → App\Models\User → UserController@show → GET /api/users/{user}`, and `reachesExposedSurface('column:users.phone')` is `true`.
- `PolicyConfiguration::fromArray()` throws `ConfigurationException` on an invalid mode (e.g. `'maybe'`).

### 5. Testing Instruction
```bash
vendor/bin/phpunit --filter PolicyEngineTest
vendor/bin/phpunit --filter DependencyGraphTest
```
Matrix assertion pattern (data-provider driven):
```php
#[DataProvider('matrixCases')]
public function test_decision_matrix(ChangeType $type, Confidence $peak, Severity $expected): void {
    $result = $this->engine->evaluate([$this->event($type)], [$this->usage($peak)], $this->graph());
    $this->assertSame($expected, $result->overall);
}
// e.g. [COLUMN_DROPPED, DEFINITIVE, BLOCK], [COLUMN_TYPE_CHANGED, DEFINITIVE, WARNING], [COLUMN_DROPPED, LOW, WARNING], [COLUMN_DROPPED, /*none*/, SAFE]
```

---

## Phase 5: Presentation & User Interface (CLI)

### 5. Objective
Make the engine usable by humans and by CI: render a color-coded Symfony Console report (status bands, impact tables, a progress indicator during indexing), wire the full `AnalysisPipeline` behind `schemaguard:check` with its real options, and implement the **CI/CD exit codes** (`0`/`1`/`2`) plus a stable `--format=json` output for automation.

### 5. Tasks

- [ ] **`src/Pipeline/AnalysisRequest.php`** — `readonly` run parameters: `scanPaths`, `migrationSource` (PENDING|EXPLICIT|GIT_DIFF), `gitBase`, `explicitMigrations`, `format` (console|json), `strict`, `useCache`. `static fromCommandOptions(array $options, PolicyConfiguration $config): self`.
- [ ] **Expand `CheckCommand` `$signature`** *(BP §3.2)* to the full option set: `--path=*`, `--migrations=*`, `--diff`, `--base=HEAD`, `--format=console`, `--strict`, `--no-cache`. `handle()` builds an `AnalysisRequest`, calls `AnalysisPipeline::run()`, passes the `PolicyResult` to `ConsoleReporter`, and returns `ExitCodeResolver::resolve()`. Wrap the pipeline call in a try/catch for `CodebaseScanException|ConfigurationException` → `renderFatal()` + return `1`.
- [ ] **`src/Pipeline/AnalysisPipeline.php`** *(BP §3.3)* — the five-stage orchestrator: discover → parse → index (once) → scan → graph+policy. Inject `MigrationDiscovery`, `MigrationParser`, `CodebaseIndexer`, `StaticAnalysisScanner`, `DependencyGraphBuilder`, `PolicyEngine`. Short-circuit to `PolicyResult::empty()` when no events.
- [ ] **Finish `MigrationDiscovery::gitDiff()`** *(BP §3.4)* — `git diff --name-only --diff-filter=AM <base> -- <migration_dirs>`, filter to existing `*.php`. (The seam the future PR-gate plugs into.)
- [ ] **`src/Output/ConsoleReporter.php`** *(BP §3.13, §7.1)* — render via `SymfonyStyle`/`Table`. Register custom formatter styles (`block` white-on-red, `warn` black-on-yellow, `safe` black-on-green, `path` cyan). Layout: header band (counts) → per-finding status block + impact `Table` (Surface | Location | Line | Confidence) + blast-radius path lines → diagnostics section → summary footer band keyed to `overall` severity. Honor `--no-ansi` (Symfony auto-detects non-TTY). Provide `renderFatal(OutputInterface, Throwable)`.
- [ ] **Progress indication**: during `CodebaseIndexer::index()`, drive a Symfony `ProgressBar` (or `withProgressBar`) over the discovered file list so large codebases show movement. Suppress the bar when `--format=json` (JSON output must be the *only* thing on stdout).
- [ ] **JSON output** *(BP §7.4)* — when `format=json`, `ConsoleReporter` emits **only** the stable schema object (`schema_version`, `overall`, `counts`, `exit_code`, `analyzed`, `findings[]`, `diagnostics[]`) as `json_encode(..., JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)`. No banners, no color.
- [ ] **`src/Output/ExitCodeResolver.php`** *(BP §3.14, §7.3)* — `resolve(PolicyResult, bool $strict): int`: `BLOCK`→1; `WARNING`→(`strict` or `treatWarningsAsFailure` ? 1 : `warningExitCode()`); `SAFE`→0.
- [ ] **`src/Exceptions/CodebaseScanException.php`** — unrecoverable scan-root problems (missing path).
- [ ] **Update `SchemaGuardServiceProvider::register()`** to bind `AnalysisPipeline` and `PolicyConfiguration` as singletons (BP §3.1).
- [ ] **`tests/Feature/CheckCommandTest.php`** — expand from the Phase 1 smoke test into the true E2E test against the fixture app.
- [ ] **`tests/Unit/Output/ExitCodeResolverTest.php`** — every row of the §7.3 table.

### 3. Internal Dependencies
- **Phase 4 complete** (`PolicyEngine` returns a `PolicyResult`; the graph supplies impact paths the reporter prints).
- All collaborators wired so `AnalysisPipeline` can be container-resolved.

### 4. Definition of Done
- Running `schemaguard:check --migrations=<drop_phone fixture> --path=tests/Fixtures` against the fixture app (where `users.phone` is used in the model + resource) renders a **`RESULT: BLOCK`** footer band, lists the model + resource usages in the impact table, prints the blast-radius path, and the process **exits `1`**.
- Running against a migration that drops an **unused** column renders **`RESULT: SAFE`** and **exits `0`**.
- A `COLUMN_TYPE_CHANGED` on a used column renders **`RESULT: WARNING`** and **exits `0`** by default; with `--strict` the same run **exits `1`**.
- `--format=json` produces parseable JSON matching the BP §7.4 schema (assert `json_decode` succeeds and `overall`/`exit_code`/`findings` are present) with **no** non-JSON output.
- The progress bar appears in console mode and is absent in JSON mode.

### 5. Testing Instruction
```bash
vendor/bin/phpunit tests/Feature/CheckCommandTest.php
vendor/bin/phpunit --filter ExitCodeResolverTest

# Manual, against the Testbench skeleton + fixtures:
vendor/bin/testbench schemaguard:check --migrations=tests/Fixtures/migrations/2024_06_01_000000_drop_phone_from_users.php --path=tests/Fixtures ; echo "exit=$?"
vendor/bin/testbench schemaguard:check --migrations=tests/Fixtures/migrations/2024_06_01_000000_drop_phone_from_users.php --path=tests/Fixtures --format=json
```
E2E assertions to include:
```php
$this->artisan('schemaguard:check', [
        '--migrations' => [$dropPhoneFixture],
        '--path'       => ['tests/Fixtures'],
     ])
     ->expectsOutputToContain('RESULT: BLOCK')
     ->assertExitCode(1);

$this->artisan('schemaguard:check', ['--migrations' => [$dropUnusedFixture], '--path' => ['tests/Fixtures']])
     ->assertExitCode(0);
```

---

## Phase 6: Edge Cases & Robustness

### 6. Objective
Close the precision and resilience gaps: scan **Raw SQL** (`DB::select`, `whereRaw`, `selectRaw`, …), handle **complex Eloquent relationships** and dynamic attributes, harden every degradation path, and lock the whole product behind a **comprehensive feature-test suite with golden files** so no future change silently regresses a verdict.

### 6. Tasks

- [ ] **`src/Scanning/Visitors/RawSqlVisitor.php`** *(BP §3.7.6, §5.2)* — scan first-string arguments of `DB::select/statement/update/insert/raw` and builder raw methods (`whereRaw/selectRaw/havingRaw/orderByRaw/groupByRaw`). Use `ColumnTokenMatcher::matchesInSql()` for whole-word, SQL-boundary matching. Confidence **capped at `HIGH`** (never `DEFINITIVE` — SQL isn't grammar-parsed in Phase 1); qualified `table.col` → `HIGH`, bare col → `MEDIUM`. Dynamic SQL (`DB::select($var)`) → `indeterminate` diagnostic. Register this visitor in `StaticAnalysisScanner`'s PASS 2.
- [ ] **Complex relationships** *(BP §5.4)* — extend `EloquentModelVisitor` + `LocalTypeResolver` for `belongsToMany` (pivot + FK args), `hasManyThrough`/`hasOneThrough`, polymorphic relations (`morphTo`/`morphMany` — handle the `*_type`/`*_id` column pair), and **relation traversal in the type resolver** (`$user->posts` → `Post`, including inside `foreach`). FK/local-key string args register as `SurfaceType::RELATION`.
- [ ] **Dynamic-attribute correctness** *(BP §5.1)* — finalize the computed-accessor disambiguation: a modern `Attribute` accessor or an `$appends` entry whose name has **no** backing-column corroboration (not in `$casts`/`$fillable`, not in any resolved query) is treated as **virtual** and must **not** produce a column-drop usage. Add fixtures for both a real attribute and a computed one.
- [ ] **Models not extending `Model` directly** *(BP §5.4)* — detect `extends Authenticatable`, `extends Pivot`, and one level of project base class (`extends BaseModel`) as Eloquent models, plus the structural-signal fallback (`$table`/`$fillable`/relationship calls present).
- [ ] **Migration robustness** *(BP §5.3)* — verify (with fixtures) that: `down()` operations never create events; multi-table `Schema::table()` closures don't leak table context; a `dropColumn` immediately re-added in the same `up()` downgrades to SAFE + diagnostic; custom `Blueprint` variable names work.
- [ ] **Degradation paths** *(BP §3.6, §5.4)* — a syntactically broken source file → `ParsedFile::failed()` + a diagnostic, run continues; the final report and JSON include an `unparsed_files` count and the diagnostic strings (honesty about coverage is a trust requirement).
- [ ] **`src/Scanning/AstCache.php`** (optional but specified, BP §3.6/§6) — cache parsed ASTs keyed by path + mtime + content hash under `config('schemaguard.cache.path')`; bypassed by `--no-cache`. Add a small cache hit/miss test.
- [ ] **Expand fixtures** under `tests/Fixtures/` — add `sql/report.php` (raw SQL with `selectRaw('phone, email')` and a `telephone` decoy), a polymorphic model, a pivot model, a `BaseModel` subclass, and a broken-syntax file.
- [ ] **Golden-file E2E** — commit a golden `expected.json` for a representative fixture scenario; `CheckCommandTest` diffs `--format=json` output against it so any layer's regression surfaces as one diff.
- [ ] **Comprehensive feature suite** — scenarios: (a) used column dropped → BLOCK/exit 1; (b) unused column dropped → SAFE/exit 0; (c) renamed column still referenced → BLOCK; (d) type change on used column → WARNING/exit 0, exit 1 under `--strict`; (e) raw-SQL-only usage of a dropped column → BLOCK (drop severity) with a raw-SQL diagnostic; (f) enforced table dropped with zero usages → BLOCK; (g) ignored column dropped despite usage → SAFE; (h) a broken source file present → run still completes with correct verdict + `unparsed_files ≥ 1`.
- [ ] **Unit tests**: `RawSqlVisitorTest` (incl. the `telephone` decoy → no match), relationship/type-resolver extensions, `AstCacheTest`.
- [ ] **Finalize `README.md`** — install, the single command, a concrete "this migration would have broken production, SchemaGuard caught it" example, the exit-code table, and a GitHub Actions snippet (`php artisan schemaguard:check --diff --base=origin/main --strict`). Document the **known limitations** (intra-procedural type resolution, raw-SQL confidence cap) so users understand WARNING vs BLOCK — this transparency is the trust posture from the strategy.

### 3. Internal Dependencies
- **Phase 5 complete** (full pipeline + reporter + exit codes — edge cases extend a working end-to-end product).
- `ColumnTokenMatcher::matchesInSql()` (built in Phase 3) is reused by `RawSqlVisitor`.

### 4. Definition of Done
- `RawSqlVisitor` matches `phone` in `selectRaw('phone, email')` (→ `RAW_SQL`/`HIGH`) but produces **zero** match for the `telephone` decoy; dynamic raw SQL yields an `indeterminate` diagnostic.
- All 8 feature scenarios (a–h) pass with the exact verdict **and** exit code specified.
- The golden-file E2E passes (byte-stable JSON for the canonical scenario).
- A deliberately broken fixture does not crash the run; the report shows `unparsed_files ≥ 1` and a diagnostic.
- Computed accessors / `$appends`-only names never trigger a column-drop usage; real attributes do.
- The **full** suite is green and coverage of `src/` meets the project threshold (target **≥ 85%** lines; the parser/scanner/policy packages **≥ 90%**).

### 5. Testing Instruction
```bash
vendor/bin/phpunit --filter RawSqlVisitorTest
vendor/bin/phpunit --testsuite Feature
vendor/bin/phpunit                      # FULL suite — must be entirely green
vendor/bin/phpunit --coverage-text      # verify coverage thresholds (requires Xdebug/PCOV)
```
Raw-SQL precision assertion to include verbatim:
```php
$usages = $this->scanRaw("DB::select('SELECT phone, email FROM users');", target: 'users.phone');
$this->assertNotEmpty($usages);
$this->assertSame(Confidence::HIGH, $usages[0]->confidence);

$decoy = $this->scanRaw("DB::select('SELECT telephone FROM directory');", target: 'users.phone');
$this->assertCount(0, $decoy, 'Substring match (telephone) must not register as a phone column usage');
```

---

## Definition of "Project Complete" (all six phases)

The package is Phase-1-shippable when:

1. `vendor/bin/phpunit` is **entirely green** with coverage at threshold.
2. `composer require schemaguard/laravel` into a fresh Laravel app, then `php artisan schemaguard:check --diff`, produces a correct, color-coded verdict and a CI-meaningful exit code — **without any code execution of the host app's migrations or models**.
3. A real destructive migration (drop of a used column) is **blocked** with a precise impact path; an additive/safe migration is **cleared**; an ambiguous reference **warns** rather than blocks.
4. The `README` carries the painful, concrete "caught before merge" example that the open-source go-to-market depends on.

> Scope reminder: GitHub App, SaaS dashboard, multi-repo, ML calibration, and non-Laravel framework parsers are **out of Phase 1**. The `--diff` flag and `--format=json` output are the deliberate seams where the future PR-gate attaches — they are present, but the integration itself is not built here.

---

*End of `IMPLEMENTATION_ROADMAP.md`. Build the phases in order, never skip a Definition of Done, and run each phase's testing instruction before moving on. Paired with `TECHNICAL_BLUEPRINT.md`, this is a complete, guess-free path from empty directory to a shippable Phase-1 product.*
