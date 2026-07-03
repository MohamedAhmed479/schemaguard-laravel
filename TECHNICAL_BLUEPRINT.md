# SchemaGuard — `TECHNICAL_BLUEPRINT.md`

> **Master Engineering Specification — Phase 1 (Local Artisan CLI)**
> Package: `schemaguard/laravel`
> Audience: an autonomous AI Coding Agent (and human reviewers) building the package from an empty directory.
> Status: Authoritative. This document is the single source of truth for Phase 1. Where this document and intuition disagree, this document wins.

---

## 0. How To Use This Blueprint (Read First)

This is an **implementation contract**, not a sketch. Every class in §3 lists its exact namespace, constructor dependencies, public/private method signatures (PHP 8.2 typed), and the value objects it consumes and produces. Every algorithm in §4 is written as numbered deterministic steps plus typed pseudo-code. Build order is in Appendix D.

**Non-negotiable engineering rules for the agent:**

1. **No execution of user code.** SchemaGuard must NEVER `include`, `require`, `eval`, instantiate, or boot any migration, model, or application class to learn about it. All knowledge is derived by **static parsing of source text into an AST**. Running a migration needs a DB connection and causes side effects; parsing does not. This is both a correctness rule and the security posture promised in the strategy (local scanning, no code execution, minimal trust surface).
2. **Determinism.** Identical inputs (same files, same config) must always produce identical output and identical exit code. No randomness, no network, no time-dependence in the decision path.
3. **Confidence over coverage.** Phase 1 deliberately under-reports rather than over-reports. A change is escalated to `BLOCK` only on **high-confidence** evidence. Ambiguous evidence produces `WARNING`. This is the core risk mitigation: developers abandon tools that block safe PRs.
4. **Fail safe, not fail loud.** A parser error on one file must never crash the run; it degrades that file to "unanalyzable" and is surfaced as a diagnostic, not a fatal.
5. **PSR-4, PSR-12, strict types.** Every PHP file begins with `declare(strict_types=1);`. Every file maps to the `SchemaGuard\` root namespace under `src/`.
6. **No placeholders.** No `// TODO`, no stubbed returns that lie about behavior. If a capability is out of Phase 1 scope, it is *absent*, not faked.

**Target platform / dependency baseline:**

| Dependency | Constraint | Why |
|---|---|---|
| PHP | `^8.2` | Enums, readonly properties, first-class callable syntax used throughout. |
| `illuminate/console` | `^11.0 \|\| ^12.0` | The Artisan command base. |
| `illuminate/support` | `^11.0 \|\| ^12.0` | `Str`, `Collection`, facade detection. |
| `illuminate/filesystem` | `^11.0 \|\| ^12.0` | File discovery. |
| `nikic/php-parser` | `^5.0` | The AST engine. **All API references in this doc are PHP-Parser v5.** |
| `symfony/console` | (transitive via Laravel) | Tables, color formatting, output styles. |
| `orchestra/testbench` (dev) | `^9.0 \|\| ^10.0` | Standard harness for testing Laravel packages in isolation. |
| `phpunit/phpunit` (dev) | `^11.0` | Test runner. |

---

## Table of Contents

1. [Executive Technical Vision & Scope](#1-executive-technical-vision--scope)
2. [Concrete Directory & File Structure](#2-concrete-directory--file-structure)
3. [Detailed Component Architecture & Deep Class Specs](#3-detailed-component-architecture--deep-class-specs)
4. [Step-by-Step Algorithmic Workflows & Pseudo-Code](#4-step-by-step-algorithmic-workflows--pseudo-code)
5. [Robust Edge Cases & Framework Quirks Handling](#5-robust-edge-cases--framework-quirks-handling)
6. [Package Configuration Schema](#6-package-configuration-schema-configschemaguardphp)
7. [CLI Output Design & CI/CD Compliance](#7-cli-output-design--cicd-compliance)
- [Appendix A — Value Object Catalog](#appendix-a--value-object-catalog)
- [Appendix B — The Confidence Model (Formal)](#appendix-b--the-confidence-model-formal)
- [Appendix C — Testing Strategy & Fixtures](#appendix-c--testing-strategy--fixtures)
- [Appendix D — Build Order / Milestones](#appendix-d--build-order--milestones)
- [Appendix E — Glossary](#appendix-e--glossary)

---

## 1. Executive Technical Vision & Scope

### 1.1 What the package *is*

SchemaGuard is a **Deployment Firewall for Database Changes**. It is not a migration runner, not a linter for code style, not a data-lineage dashboard, and not an AI wrapper. Its single function is to answer one question, deterministically, before code merges:

> *"Does this database schema change break code, queries, or exposed surfaces that depend on the affected table/column?"*

…and to return one of three verdicts: **`SAFE`**, **`WARNING`**, or **`BLOCK`**.

The moat is **semantic understanding**: parsing migrations into typed *schema change events*, parsing the application into an *Abstract Syntax Tree*, resolving which events touch symbols that the AST proves are in use, and applying a deterministic policy. Regex is explicitly rejected as the primary mechanism (it produces unacceptable false-positive rates); the AST + a lightweight local type resolver is the engine.

### 1.2 Phase 1 scope — exactly what ships

Phase 1 is a **purely local Artisan CLI tool**. There is no GitHub App, no SaaS, no network, no persistence beyond an optional cache file. The single public entry point is:

```bash
php artisan schemaguard:check
```

**Detected schema change event types (the *only* four in Phase 1):**

| Event (`ChangeType` enum case) | Migration source pattern (canonical) | Default base severity |
|---|---|---|
| `COLUMN_DROPPED` | `$table->dropColumn('phone')` / `dropColumn(['a','b'])` | High → `BLOCK` if used |
| `COLUMN_RENAMED` | `$table->renameColumn('old', 'new')` | High → `BLOCK` if old name used |
| `TABLE_DROPPED` | `Schema::drop('t')` / `Schema::dropIfExists('t')` | Critical → `BLOCK` if used |
| `COLUMN_TYPE_CHANGED` | `$table->string('email')->change()` | Medium → `WARNING` even if used |

**Detected usage surfaces (where a column/table is "in use"):**

1. **Eloquent Models** — `$fillable`, `$guarded`, `$casts`, `$appends`, `$hidden`, `$visible`, `$dates`, accessor/mutator method names (both legacy `getXAttribute`/`setXAttribute` and the modern `Attribute`-return style), relationship foreign-key arguments, and local query scopes.
2. **API Resources** — classes extending `JsonResource` / `ResourceCollection`; column references inside `toArray()`.
3. **Controllers** — validation rule keys, request input access, and Eloquent query usage.
4. **Routes** — `routes/*.php`, used to connect a controller action to an HTTP endpoint for blast-radius reporting (`Column → Model → Controller → Route`).
5. **Raw SQL** — `DB::select()`, `DB::statement()`, `whereRaw()`, `selectRaw()`, `havingRaw()`, `orderByRaw()` — scanned as opaque strings with word-boundary token matching at **reduced confidence**.

**Explicitly OUT of Phase 1 scope** (documented so the agent does not build them): GitHub/GitLab integration, PR diff ingestion as a service, multi-repo dashboards, machine-learned calibration, cross-framework parsers (Rails/Django/Prisma), nullability/index-change detection, cross-file/inter-procedural type flow, and any cloud telemetry. The architecture must *leave room* for these (clean seams), but Phase 1 must not contain partial implementations of them.

### 1.3 The decision pipeline (one sentence)

```
Discover target migrations → parse them into SchemaChangeEvent[] →
index the codebase into an AST symbol map → scan for Usage[] of each event's symbol →
build a DependencyGraph (impact paths) → PolicyEngine evaluates (event × usage-confidence × config) →
ConsoleReporter renders → process exits with a CI-meaningful code.
```

---

## 2. Concrete Directory & File Structure

This is the exact skeleton the agent must scaffold. Directories are grouped by **architectural layer**, not by Laravel convention alone, because the layers (parse / scan / graph / policy / output) are the moat and must be independently testable.

```
schemaguard-laravel/
├── composer.json
├── README.md
├── LICENSE.md                              # MIT — open-source wedge requires permissive license
├── phpunit.xml.dist
├── .gitattributes                          # export-ignore tests/, fixtures from the dist tarball
│
├── config/
│   └── schemaguard.php                     # Published config (full template in §6)
│
├── src/
│   ├── SchemaGuardServiceProvider.php      # §3.1 — bootstrap, config merge/publish, command registration
│   │
│   ├── Console/
│   │   └── Commands/
│   │       └── CheckCommand.php            # §3.2 — `schemaguard:check` entry point; orchestrates, never analyzes
│   │
│   ├── Pipeline/
│   │   └── AnalysisPipeline.php            # §3.3 — pure orchestrator: parse → scan → graph → policy → result
│   │
│   ├── Migrations/                         # ── PARSER LAYER ──
│   │   ├── MigrationDiscovery.php          # §3.4 — decides WHICH migration files to analyze
│   │   ├── MigrationParser.php             # §3.5 — file text → SchemaChangeEvent[]
│   │   └── Visitors/
│   │       └── SchemaCallVisitor.php       # §3.5.3 — AST visitor capturing Schema::/Blueprint calls
│   │
│   ├── Scanning/                           # ── SCANNER LAYER (the AST core) ──
│   │   ├── CodebaseIndexer.php             # §3.6 — discovers + parses app PHP files into ParsedFile[]
│   │   ├── StaticAnalysisScanner.php       # §3.7 — runs all usage visitors, returns Usage[]
│   │   ├── ParsedFile.php                  # value object: path + AST root + parse status
│   │   ├── LocalTypeResolver.php           # §3.8 — intra-method variable→class inference
│   │   ├── ColumnTokenMatcher.php          # §3.9 — rarity heuristic + word-boundary raw-SQL matching
│   │   └── Visitors/
│   │       ├── AbstractUsageVisitor.php    # shared base: parent links, FQ-name access, match emission
│   │       ├── EloquentModelVisitor.php    # §3.7.1 — model column declarations + table binding
│   │       ├── EloquentUsageVisitor.php    # §3.7.2 — $model->col, Model::where('col'), builder chains
│   │       ├── ApiResourceVisitor.php      # §3.7.3 — JsonResource::toArray() column exposure
│   │       ├── ControllerVisitor.php       # §3.7.4 — validation + request input + queries
│   │       ├── RouteVisitor.php            # §3.7.5 — controller@action ↔ HTTP verb/URI
│   │       └── RawSqlVisitor.php           # §3.7.6 — DB::select/whereRaw/selectRaw string scanning
│   │
│   ├── Graph/                              # ── DEPENDENCY GRAPH LAYER ──
│   │   ├── DependencyGraph.php             # §3.10 — adjacency-list store + reachability
│   │   ├── DependencyGraphBuilder.php      # §3.11 — assembles nodes/edges from models, usages, routes
│   │   ├── GraphNode.php                   # value object: typed node (Column/Table/Model/.../Route)
│   │   └── ImpactPath.php                  # value object: an ordered chain from column → exposed surface
│   │
│   ├── Policy/                             # ── POLICY LAYER ──
│   │   ├── PolicyEngine.php                # §3.12 — deterministic verdict computation
│   │   ├── PolicyResult.php                # §3.12.4 — aggregate result + per-event findings
│   │   ├── EventFinding.php                # value object: one event + its usages + computed severity
│   │   └── PolicyConfiguration.php         # typed wrapper around config/schemaguard.php
│   │
│   ├── ValueObjects/                       # ── SHARED IMMUTABLE TYPES (Appendix A) ──
│   │   ├── SchemaChangeEvent.php
│   │   ├── ChangeType.php                  # enum: COLUMN_DROPPED | COLUMN_RENAMED | TABLE_DROPPED | COLUMN_TYPE_CHANGED
│   │   ├── ColumnReference.php             # (table, column) normalized identity
│   │   ├── TableReference.php
│   │   ├── Usage.php                       # one detected reference: location + surface + confidence
│   │   ├── SourceLocation.php              # file path + line + (optional) column
│   │   ├── Confidence.php                  # enum: DEFINITIVE | HIGH | MEDIUM | LOW
│   │   ├── Severity.php                    # enum: SAFE | WARNING | BLOCK (ordered)
│   │   └── SurfaceType.php                 # enum: MODEL_SCHEMA | ELOQUENT_QUERY | API_RESOURCE | CONTROLLER | RAW_SQL | RELATION
│   │
│   ├── Output/
│   │   ├── ConsoleReporter.php             # §3.13 — Symfony Console rendering (tables, status blocks)
│   │   └── ExitCodeResolver.php            # §3.14 — PolicyResult + config → int exit code
│   │
│   └── Exceptions/
│       ├── SchemaGuardException.php        # base; all package exceptions extend this
│       ├── MigrationParseException.php     # thrown internally, caught + degraded — never fatal to a run
│       ├── ConfigurationException.php      # invalid config (e.g., unknown policy mode)
│       └── CodebaseScanException.php       # unrecoverable scan-root problem (e.g., app path missing)
│
└── tests/
    ├── TestCase.php                        # extends Orchestra\Testbench\TestCase; registers the provider
    ├── Unit/
    │   ├── Migrations/
    │   │   ├── MigrationParserTest.php
    │   │   └── MigrationDiscoveryTest.php
    │   ├── Scanning/
    │   │   ├── EloquentModelVisitorTest.php
    │   │   ├── EloquentUsageVisitorTest.php
    │   │   ├── ApiResourceVisitorTest.php
    │   │   ├── RawSqlVisitorTest.php
    │   │   ├── LocalTypeResolverTest.php
    │   │   └── ColumnTokenMatcherTest.php
    │   ├── Graph/
    │   │   └── DependencyGraphTest.php
    │   └── Policy/
    │       ├── PolicyEngineTest.php
    │       └── ExitCodeResolverTest.php
    ├── Feature/
    │   └── CheckCommandTest.php             # end-to-end: fixtures in, exit code + output asserted
    └── Fixtures/                            # realistic mini-app for E2E tests (NOT executed, only parsed)
        ├── migrations/
        │   ├── 2024_01_01_000000_create_users_table.php
        │   ├── 2024_06_01_000000_drop_phone_from_users.php       # anonymous class
        │   ├── 2024_06_02_000000_rename_users_column.php         # renameColumn
        │   ├── 2024_06_03_000000_drop_legacy_logs_table.php      # dropIfExists
        │   └── 2024_06_04_000000_change_email_type.php           # ->change()
        ├── Models/
        │   ├── User.php                     # accessors, casts, fillable, relations, scopes
        │   └── Post.php
        ├── Http/
        │   ├── Controllers/UserController.php
        │   └── Resources/UserResource.php
        ├── routes/api.php
        └── sql/report.php                   # DB::select with raw column references
```

**Auto-discovery wiring** — `composer.json` must declare the provider so the host app needs zero manual registration:

```json
{
  "name": "schemaguard/laravel",
  "description": "A deployment firewall for database schema changes. Blocks destructive Laravel migrations before they reach production.",
  "license": "MIT",
  "require": {
    "php": "^8.2",
    "illuminate/console": "^11.0 || ^12.0",
    "illuminate/support": "^11.0 || ^12.0",
    "illuminate/filesystem": "^11.0 || ^12.0",
    "nikic/php-parser": "^5.0"
  },
  "require-dev": {
    "orchestra/testbench": "^9.0 || ^10.0",
    "phpunit/phpunit": "^11.0"
  },
  "autoload": { "psr-4": { "SchemaGuard\\": "src/" } },
  "autoload-dev": { "psr-4": { "SchemaGuard\\Tests\\": "tests/" } },
  "extra": {
    "laravel": {
      "providers": ["SchemaGuard\\SchemaGuardServiceProvider"]
    }
  },
  "minimum-stability": "stable"
}
```

---

## 3. Detailed Component Architecture & Deep Class Specs

> Convention: every class is `final` unless it is an `abstract` base. Constructor dependencies are injected (Laravel container resolves them); no `new` inside business logic except for value objects. All value objects are `readonly`.

### 3.1 `SchemaGuardServiceProvider`

**Namespace:** `SchemaGuard\SchemaGuardServiceProvider`
**Extends:** `Illuminate\Support\ServiceProvider`
**Responsibility:** Bootstrap only. Merge default config, publish config, register the command. It contains **zero** analysis logic.

```php
final class SchemaGuardServiceProvider extends ServiceProvider
{
    /** Merge package defaults so config('schemaguard.*') always resolves, even unpublished. */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/schemaguard.php', 'schemaguard');

        // Bind the typed config wrapper as a singleton so every layer reads one normalized source.
        $this->app->singleton(PolicyConfiguration::class, fn ($app) =>
            PolicyConfiguration::fromArray($app['config']->get('schemaguard'))
        );

        // The pipeline and its collaborators are stateless → safe as singletons.
        $this->app->singleton(AnalysisPipeline::class);
    }

    /** Only runs in console; publishes config and registers the command. */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__ . '/../config/schemaguard.php' => config_path('schemaguard.php')],
                'schemaguard-config'
            );
            $this->commands([CheckCommand::class]);
        }
    }
}
```

**Key decisions:**
- `mergeConfigFrom` guarantees the package works *before* `vendor:publish` — critical for the "drop in and run" open-source adoption story.
- `PolicyConfiguration` is bound once and injected everywhere; no layer reads `config()` directly (keeps layers framework-decoupled and unit-testable with a plain array).

---

### 3.2 `CheckCommand`

**Namespace:** `SchemaGuard\Console\Commands\CheckCommand`
**Extends:** `Illuminate\Console\Command`
**Responsibility:** CLI surface + orchestration glue. It parses options, invokes the `AnalysisPipeline`, hands the result to `ConsoleReporter`, and returns the resolved exit code. It performs **no** parsing/scanning itself.

**Signature & inputs:**

```php
final class CheckCommand extends Command
{
    protected $signature = 'schemaguard:check
        {--path=* : Restrict the codebase scan to these directories (repeatable). Defaults to config.}
        {--migrations=* : Analyze these specific migration files instead of auto-discovery.}
        {--diff : Discover changed migrations via `git diff` instead of the pending set.}
        {--base=HEAD : Git ref to diff against when --diff is used.}
        {--format=console : Output format. console|json.}
        {--strict : Treat WARNING as failure for this run (overrides config exit mapping).}
        {--no-cache : Bypass the AST parse cache.}';

    protected $description = 'Analyze pending/changed migrations and block destructive schema changes that break the codebase.';

    public function __construct(
        private readonly AnalysisPipeline $pipeline,
        private readonly ConsoleReporter $reporter,
        private readonly ExitCodeResolver $exitResolver,
        private readonly PolicyConfiguration $config,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $request = AnalysisRequest::fromCommandOptions($this->options(), $this->config);

        try {
            $result = $this->pipeline->run($request);   // PolicyResult
        } catch (CodebaseScanException | ConfigurationException $e) {
            $this->reporter->renderFatal($this->output, $e);
            return 1; // configuration/environment failure is a hard fail in CI
        }

        $this->reporter->render($this->output, $result, $request);

        return $this->exitResolver->resolve($result, $request->strict);
    }
}
```

**`AnalysisRequest`** (value object, `Pipeline/` or `ValueObjects/`): normalized run parameters — scan paths, migration-source strategy (`PENDING` | `EXPLICIT` | `GIT_DIFF`), git base ref, output format, strict flag, cache flag. Built once from CLI options + config so the pipeline never touches `$this->option()`.

---

### 3.3 `AnalysisPipeline`

**Namespace:** `SchemaGuard\Pipeline\AnalysisPipeline`
**Responsibility:** The pure five-stage orchestrator. Stateless. This is the function that the README's "Core Flow" diagram literally maps to.

```php
final class AnalysisPipeline
{
    public function __construct(
        private readonly MigrationDiscovery $discovery,
        private readonly MigrationParser $migrationParser,
        private readonly CodebaseIndexer $indexer,
        private readonly StaticAnalysisScanner $scanner,
        private readonly DependencyGraphBuilder $graphBuilder,
        private readonly PolicyEngine $policy,
    ) {}

    public function run(AnalysisRequest $request): PolicyResult
    {
        // STAGE 1 — Which migrations?
        $migrationFiles = $this->discovery->resolve($request);          // string[] absolute paths

        // STAGE 2 — Parse migrations → typed events.
        $events = $this->migrationParser->parseMany($migrationFiles);   // SchemaChangeEvent[]
        if ($events === []) {
            return PolicyResult::empty();                               // nothing destructive → SAFE, exit 0
        }

        // STAGE 3 — Index the codebase once (parse every app PHP file to an AST).
        $index = $this->indexer->index($request->scanPaths);            // ParsedFile[] keyed by path

        // STAGE 4 — Scan for usages of the symbols touched by the events.
        $targets = SymbolTargetSet::fromEvents($events);                // distinct tables + columns to hunt for
        $usages  = $this->scanner->scan($index, $targets);              // Usage[]

        // STAGE 5 — Build the dependency graph (impact paths) + evaluate policy.
        $graph   = $this->graphBuilder->build($index, $usages);         // DependencyGraph
        return $this->policy->evaluate($events, $usages, $graph);       // PolicyResult
    }
}
```

The pipeline indexes the codebase **once** and reuses the AST across every visitor and every event — parsing is the expensive step, so it must not be repeated per event.

---

### 3.4 `MigrationDiscovery`

**Namespace:** `SchemaGuard\Migrations\MigrationDiscovery`
**Responsibility:** Decide *which* migration files Phase 1 analyzes. Three strategies, selected by `AnalysisRequest::$migrationSource`.

```php
final class MigrationDiscovery
{
    public function __construct(
        private readonly Filesystem $files,                 // Illuminate\Filesystem\Filesystem
        private readonly PolicyConfiguration $config,
    ) {}

    /** @return string[] absolute migration file paths, sorted ascending by name. */
    public function resolve(AnalysisRequest $request): array { /* dispatch on strategy */ }

    /** EXPLICIT: user passed --migrations=… ; validate each exists and is *.php. */
    private function explicit(array $paths): array { /* ... */ }

    /** GIT_DIFF: `git diff --name-only --diff-filter=AM {base} -- database/migrations`. */
    private function gitDiff(string $base, array $migrationDirs): array { /* ... */ }

    /** PENDING: all files in configured migration dirs (default strategy in Phase 1). */
    private function pending(array $migrationDirs): array { /* ... */ }
}
```

**Strategy details:**
- **`PENDING` (default):** glob every configured migration directory (`config('schemaguard.migration_paths')`, default `database/migrations`) for `*.php`. Phase 1 deliberately does **not** read the `migrations` DB table (that needs a DB connection and breaks the "no environment dependency" rule); instead it treats the diffable/changed set as the unit of analysis. For a clean local default it analyzes the full migration directory but **only emits events for destructive operations**, so non-destructive history is silently ignored.
- **`GIT_DIFF` (`--diff`):** shell out to `git diff --name-only --diff-filter=AM <base> -- <migration_dirs>`. `--diff-filter=AM` keeps Added+Modified, drops deletions. This is the CI-realistic mode and the seam where the future PR gate plugs in.
- **`EXPLICIT` (`--migrations=`):** exact files; used by tests and power users.

Self-describing operations (`dropColumn`, `renameColumn`, `dropIfExists`, `->change()`) carry all needed information *inside the migration call itself*, so Phase 1 needs no schema diff against the live DB to detect the four event types — the file is sufficient.

---

### 3.5 `MigrationParser`

**Namespace:** `SchemaGuard\Migrations\MigrationParser`
**Responsibility:** Convert a migration file's source text into a list of `SchemaChangeEvent`. The single most important parser-layer class.

```php
final class MigrationParser
{
    private \PhpParser\Parser $parser;

    public function __construct(private readonly Filesystem $files)
    {
        // PHP-Parser v5: build a parser for the newest PHP grammar the host supports.
        $this->parser = (new \PhpParser\ParserFactory)->createForNewestSupportedVersion();
    }

    /** @param string[] $paths @return SchemaChangeEvent[] */
    public function parseMany(array $paths): array
    {
        return array_merge(...array_map($this->parseFile(...), $paths));
    }

    /** @return SchemaChangeEvent[] — empty on parse failure (degrade, never throw to caller). */
    public function parseFile(string $path): array
    {
        try {
            $ast = $this->parser->parse($this->files->get($path));
        } catch (\PhpParser\Error $e) {
            // Surface as a diagnostic upstream; a single unparseable migration must not abort the run.
            return [];
        }

        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver());      // FQ names for Schema facade
        $traverser->addVisitor(new \PhpParser\NodeVisitor\ParentConnectingVisitor()); // parent links
        $collector = new SchemaCallVisitor($path);
        $traverser->addVisitor($collector);
        $traverser->traverse($ast);

        return $collector->events();
    }
}
```

#### 3.5.1 Why AST, formally

The four event types must be extracted from migrations that may be:
- **Anonymous-class migrations** (Laravel 9+ default): `return new class extends Migration { public function up(): void {…} };`
- **Named-class migrations** (legacy): `class DropPhoneFromUsers extends Migration { public function up() {…} }`

Both forms place the schema operations inside the `up()` method body. A regex over the raw text cannot reliably (a) scope to `up()` vs `down()`, (b) handle multi-line/closure call chains, or (c) distinguish `dropColumn` from a string literal `'dropColumn'`. The AST gives exact node identity and scope.

#### 3.5.2 The grammar we are matching (concrete AST shapes)

| Source | AST shape (v5 node classes) |
|---|---|
| `Schema::table('users', function (Blueprint $t) {…})` | `Expr\StaticCall` on `Name('Illuminate\Support\Facades\Schema')`, `name='table'`, `args[0]=Scalar\String_`, `args[1]=Expr\Closure` |
| `$t->dropColumn('phone')` | `Expr\MethodCall` where `var` is `Expr\Variable('t')`, `name='dropColumn'`, `args[0]=String_` **or** `Expr\Array_` |
| `$t->renameColumn('a','b')` | `Expr\MethodCall`, `name='renameColumn'`, `args[0]=String_`, `args[1]=String_` |
| `Schema::drop('logs')` / `dropIfExists('logs')` | `Expr\StaticCall`, `name in {drop, dropIfExists}`, `args[0]=String_` |
| `$t->string('email')->change()` | outer `Expr\MethodCall name='change'`; its `var` is a `MethodCall` whose `name` is a known column-type method (`string`,`integer`,…) and `args[0]=String_` (the column) |

#### 3.5.3 `SchemaCallVisitor`

**Namespace:** `SchemaGuard\Migrations\Visitors\SchemaCallVisitor`
**Extends:** `PhpParser\NodeVisitorAbstract`
**Responsibility:** Walk the AST and emit `SchemaChangeEvent`s, while tracking the **table context** established by the enclosing `Schema::table()`/`Schema::create()` call and the `Blueprint` variable name.

State it maintains:
- `?string $currentTable` — the table named by the nearest enclosing `Schema::table('…')`.
- `?string $blueprintVar` — the parameter name of the closure's `Blueprint` argument (usually `table`, but the user may name it `$t` or `$blueprint`; resolve from the closure's `params`, not by assuming `$table`).
- `bool $insideUp` — only collect events when the cursor is within the `up()` method (ignore `down()`; a `dropColumn` in `down()` is the *rollback* of an additive change, not a forward-destructive change).

Core method outline (real signatures, logic specified — not stubbed):

```php
final class SchemaCallVisitor extends NodeVisitorAbstract
{
    /** @var SchemaChangeEvent[] */
    private array $events = [];
    private ?string $currentTable = null;
    private ?string $blueprintVar = null;
    private bool $insideUp = false;

    public function __construct(private readonly string $filePath) {}

    public function enterNode(Node $node)
    {
        // (1) Track up() scope.
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === 'up') {
            $this->insideUp = true;
        }
        if (!$this->insideUp) {
            return null;
        }

        // (2) Enter Schema::table/create → set table context + blueprint var from the closure param.
        if ($node instanceof Node\Expr\StaticCall && $this->isSchemaFacade($node)) {
            $method = $node->name->toString();

            if (in_array($method, ['table', 'create'], true) && isset($node->args[0])) {
                $this->currentTable = $this->literalString($node->args[0]->value);
                $this->blueprintVar = $this->resolveBlueprintVar($node->args[1] ?? null);
            }

            // (3) TABLE_DROPPED — Schema::drop / dropIfExists.
            if (in_array($method, ['drop', 'dropIfExists'], true) && isset($node->args[0])) {
                $table = $this->literalString($node->args[0]->value);
                if ($table !== null) {
                    $this->events[] = SchemaChangeEvent::tableDropped(
                        new TableReference($table),
                        SourceLocation::fromNode($this->filePath, $node)
                    );
                }
            }
        }

        // (4) Blueprint method calls on the tracked variable.
        if ($node instanceof Node\Expr\MethodCall && $this->isBlueprintCall($node)) {
            $this->handleBlueprintCall($node);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toString() === 'up') {
            $this->insideUp = false;
        }
        // Reset table context when leaving a Schema:: closure so sibling tables don't bleed.
        if ($node instanceof Node\Expr\Closure && $this->isInsideSchemaClosure($node)) {
            $this->currentTable = null;
            $this->blueprintVar = null;
        }
        return null;
    }

    public function events(): array { return $this->events; }

    // --- helpers (each fully implemented in code) ---
    // isSchemaFacade(StaticCall): bool      — class name resolves to Schema facade (FQ via NameResolver)
    // isBlueprintCall(MethodCall): bool     — receiver is Variable($this->blueprintVar) OR a fluent chain rooted there
    // resolveBlueprintVar(?Arg closure): ?string — read Closure->params[0]->var->name
    // literalString(Expr): ?string          — String_ → value; anything dynamic → null (see §4.2 dynamic handling)
    // handleBlueprintCall(MethodCall): void — the dispatch in §4.1
}
```

**Dynamic-argument guard:** `literalString()` returns `null` for any non-`String_` argument (e.g. `$table->dropColumn($columnName)` where the name is a variable, or a concatenation). A `null` table/column means SchemaGuard cannot statically know the symbol; that event is recorded as **`indeterminate`** with `Confidence::LOW` and surfaced as a WARNING-class diagnostic ("dynamic schema operation, manual review advised") rather than silently dropped. Pretending to know a dynamic name would create false negatives — the most dangerous failure mode.

---

### 3.6 `CodebaseIndexer`

**Namespace:** `SchemaGuard\Scanning\CodebaseIndexer`
**Responsibility:** Discover all application PHP files under the configured scan paths, parse each into an AST **once**, and return an index of `ParsedFile` objects. Honors ignore globs. Optionally caches parsed ASTs keyed by file mtime+hash.

```php
final class CodebaseIndexer
{
    private \PhpParser\Parser $parser;

    public function __construct(
        private readonly Filesystem $files,
        private readonly PolicyConfiguration $config,
        private readonly ?AstCache $cache = null,        // null when --no-cache
    ) {
        $this->parser = (new \PhpParser\ParserFactory)->createForNewestSupportedVersion();
    }

    /** @param string[] $scanPaths @return array<string,ParsedFile> keyed by absolute path */
    public function index(array $scanPaths): array
    {
        $index = [];
        foreach ($this->discoverPhpFiles($scanPaths) as $path) {
            if ($this->config->isIgnored($path)) {
                continue;
            }
            $index[$path] = $this->parse($path);
        }
        return $index;
    }

    private function parse(string $path): ParsedFile
    {
        // cache hit?
        $source = $this->files->get($path);
        try {
            $ast = $this->parser->parse($source);
            // Decorate every AST once with FQ names + parent links so visitors share the work.
            $traverser = new NodeTraverser(
                new NodeVisitor\NameResolver(),
                new NodeVisitor\ParentConnectingVisitor(),
            );
            $ast = $traverser->traverse($ast);
            return ParsedFile::parsed($path, $ast);
        } catch (\PhpParser\Error $e) {
            return ParsedFile::failed($path, $e->getMessage());   // degraded, not fatal
        }
    }

    /** @return iterable<string> */
    private function discoverPhpFiles(array $scanPaths): iterable { /* recursive *.php glob */ }
}
```

The `NameResolver` and `ParentConnectingVisitor` are applied **here, once per file**, and the decorated AST is reused by every usage visitor; this avoids re-resolving names six times per file.

`ParsedFile` (value object): `path`, `?array $ast`, `bool $parsed`, `?string $error`. Visitors skip `!$parsed` files; the count of failed files is reported as a diagnostic so users know coverage was incomplete (honesty about analysis limits is a stated trust requirement).

---

### 3.7 `StaticAnalysisScanner`

**Namespace:** `SchemaGuard\Scanning\StaticAnalysisScanner`
**Responsibility:** The core engine. Given the parsed index and the set of target symbols, run each specialized `NodeVisitor` over every parsed file and collect `Usage` objects. It is a **coordinator**: each visitor owns one surface.

```php
final class StaticAnalysisScanner
{
    public function __construct(
        private readonly LocalTypeResolver $typeResolver,
        private readonly ColumnTokenMatcher $tokenMatcher,
        private readonly ModelTableMap $modelTableMap,   // built lazily on first model encountered
    ) {}

    /** @param array<string,ParsedFile> $index @return Usage[] */
    public function scan(array $index, SymbolTargetSet $targets): array
    {
        // PASS 1 — build the model↔table map (needed by every other visitor to bind columns to tables).
        foreach ($index as $file) {
            if (!$file->parsed) { continue; }
            $this->modelTableMap->ingest($file);     // EloquentModelVisitor in "registration" mode
        }

        // PASS 2 — run all usage visitors with full model/table knowledge available.
        $usages = [];
        foreach ($index as $file) {
            if (!$file->parsed) { continue; }
            foreach ($this->visitorsFor($targets) as $visitor) {
                $usages = array_merge($usages, $this->runVisitor($visitor, $file, $targets));
            }
        }
        return $this->dedupe($usages);   // collapse identical (symbol, location) pairs, keep max confidence
    }

    /** @return AbstractUsageVisitor[] */
    private function visitorsFor(SymbolTargetSet $targets): array { /* instantiate the six visitors */ }

    private function runVisitor(AbstractUsageVisitor $v, ParsedFile $file, SymbolTargetSet $t): array
    {
        $v->reset($file, $t);
        (new NodeTraverser($v))->traverse($file->ast);
        return $v->usages();
    }
}
```

**Two-pass requirement (critical):** the scanner runs models **first** to build `ModelTableMap` (class FQN → table name), because `EloquentUsageVisitor`, `ApiResourceVisitor`, and `ControllerVisitor` all need to know which table a `User` instance maps to before they can bind a column reference to a target. A single pass would miss usages in files alphabetically earlier than the model.

#### 3.7.1 `EloquentModelVisitor`

**Detects:** column declarations *inside a model class*, and the model→table binding. Runs in two modes: **registration** (just resolve class FQN + table) and **usage** (emit `Usage` for each target column found in a binding position).

Table resolution rule (must replicate Eloquent's own logic):
1. If the class declares `protected $table = 'foo';` → table is `foo`.
2. Else table = `Str::snake(Str::pluralStudly(class_basename($fqcn)))`. (e.g. `User` → `users`, `BlogPost` → `blog_posts`.) Use `Illuminate\Support\Str` so behavior matches the framework exactly.

Column-bearing positions detected (each emits a `Usage` with `SurfaceType::MODEL_SCHEMA` and `Confidence::DEFINITIVE`, because position inside a table-bound model is conclusive):

| Position | AST detection | Column source |
|---|---|---|
| `$fillable` / `$guarded` | `Stmt\Property` named `fillable`/`guarded`, value `Array_` | each `String_` item |
| `$casts` | `Property` named `casts`, value `Array_` | each `ArrayItem->key` String_ |
| `$appends`, `$hidden`, `$visible`, `$dates` | same, by name | each String_ item |
| Legacy accessor | `ClassMethod` matching `/^get(.+)Attribute$/` | `Str::snake($1)` |
| Legacy mutator | `ClassMethod` matching `/^set(.+)Attribute$/` | `Str::snake($1)` |
| Modern accessor | `ClassMethod` with return type `Attribute` (FQ `Illuminate\Database\Eloquent\Casts\Attribute`) | `Str::snake(methodName)` |
| Relationship FK | `MethodCall` to `hasMany/hasOne/belongsTo/belongsToMany/...` | `args[1]`/`args[2]` String_ (foreign/local key); emits `SurfaceType::RELATION` |
| Local scope query | `ClassMethod` matching `/^scope.+/` containing builder calls | columns via `EloquentUsageVisitor` sub-walk |

> **Modern accessor caveat:** the method name *is* the column for `Attribute`-style accessors, but a method named `fullName(): Attribute` returning a *computed* value over `first_name`/`last_name` does **not** mean a `full_name` column exists. Therefore a modern-accessor match is `Confidence::HIGH` (not `DEFINITIVE`) unless the same column also appears in `$casts`/`$fillable`. See §5.1.

#### 3.7.2 `EloquentUsageVisitor`

**Detects:** column references in *query* and *attribute-access* positions, anywhere in the codebase. This is the highest-value and highest-false-positive surface; its precision comes entirely from `LocalTypeResolver` (§3.8) and `ColumnTokenMatcher` (§3.9).

Two reference families:

**(A) Attribute access — `$x->phone`** (`Expr\PropertyFetch`):
- Resolve the type of `$x` via `LocalTypeResolver`.
- If `$x` resolves to a model FQN whose table is a target table, and `phone` is a target column of that table → `Usage(ELOQUENT_QUERY, DEFINITIVE)`.
- If `$x` is **unresolved** → consult `ColumnTokenMatcher`: emit `Confidence::MEDIUM` if the column name is "rare", `Confidence::LOW` if "common". Never emit nothing for unresolved (that would risk a false negative on the destructive path), but never emit `HIGH` either.

**(B) Query-builder string columns** (`Expr\MethodCall` to a known column-accepting builder method):
- Builder column methods (closed set, configurable): `where, orWhere, whereNot, whereColumn, whereIn, whereNotIn, whereNull, whereNotNull, whereBetween, having, select, addSelect, orderBy, orderByDesc, groupBy, pluck, value, increment, decrement, sum, avg, max, min, oldest, latest, firstWhere, updateOrCreate(keys), firstOrCreate(keys)`.
- Extract the column literal from the method's argument(s) per a per-method arg-position map (e.g. `where`'s column is `args[0]`; `select`'s columns are all string args / array items).
- Resolve the **receiver chain root**: `User::where(...)` → root is `User` (StaticCall on a model class); `DB::table('users')->where(...)` → root table is the literal `'users'`; `$user->where(...)` → resolve `$user` via type resolver; `$this->where(...)` inside a model → that model's table.
- Confidence: receiver provably bound to target table → `DEFINITIVE`/`HIGH`; receiver unresolved → fall to the token-rarity tier as in (A).

#### 3.7.3 `ApiResourceVisitor`

**Detects:** columns *exposed through an API* — the most business-critical surface, because breaking these breaks external consumers.
- Identify classes whose `extends` resolves to `Illuminate\Http\Resources\Json\JsonResource` or `ResourceCollection`.
- Within `toArray()`, detect `$this->phone` (`PropertyFetch` on `$this`) and array values `'label' => $this->phone`.
- Inside a resource, `$this->{col}` proxies the wrapped model's attribute, so a `$this->phone` where the resource's associated model maps to a target table → `Usage(API_RESOURCE, DEFINITIVE)`.
- **Associated-model inference:** map a `UserResource` to the `User` model heuristically by name (`{Model}Resource`) *and* corroborate via the `@mixin User` docblock or the constructor's typed `$resource` if present. If the model cannot be inferred, the column is still recorded against *all* target tables that declare that column, at `Confidence::HIGH`, because API exposure is too important to downgrade on a naming miss.

#### 3.7.4 `ControllerVisitor`

**Detects:**
- **Validation rules** — array keys in `$request->validate([...])`, `Validator::make($data, [...])`, and FormRequest `rules()` returns. A key `'phone' => 'required'` referencing a target column → `Usage(CONTROLLER, HIGH)` (validation strongly implies the field is persisted, but it could be an input-only field — hence HIGH not DEFINITIVE).
- **Request input** — `$request->input('phone')`, `$request->get('phone')`, `$request->only([...])`, `$request->phone` → `Usage(CONTROLLER, MEDIUM)` (request fields don't always map 1:1 to columns).
- **Eloquent queries** inside actions — delegated to a `EloquentUsageVisitor` sub-walk.
- Emits the controller class + method so `DependencyGraphBuilder` can link to routes.

#### 3.7.5 `RouteVisitor`

**Detects:** the `controller@action → HTTP verb + URI` mapping, by scanning `routes/*.php` for `Route::get/post/put/patch/delete(...)` and `Route::apiResource/resource(...)`. Produces `RouteBinding` records (verb, uri, controllerFqcn, method). This visitor **emits no `Usage`** — it feeds the graph so a column's impact can be reported as a concrete endpoint (e.g. `GET /api/users/{user}`).

#### 3.7.6 `RawSqlVisitor`

**Detects:** target columns/tables inside raw SQL strings:
- `DB::select(...)`, `DB::statement(...)`, `DB::update(...)`, `DB::insert(...)`, `DB::raw(...)`
- Builder raw methods: `whereRaw`, `selectRaw`, `havingRaw`, `orderByRaw`, `groupByRaw`
- The first string argument is treated as opaque SQL. `ColumnTokenMatcher::matchesInSql()` searches for the target token with **word boundaries adjacent to SQL syntax** (preceded/followed by whitespace, comma, `(`, `)`, `.`, `=`, backtick, or string end) to avoid matching `phone` inside `telephone`.
- Confidence is capped at **`HIGH`** for an exact whole-word match next to a SQL keyword/operator, and `MEDIUM` otherwise. Raw SQL can never be `DEFINITIVE` in Phase 1 because the string is not semantically parsed (we don't run a SQL grammar). See §5.2.

---

### 3.8 `LocalTypeResolver`

**Namespace:** `SchemaGuard\Scanning\LocalTypeResolver`
**Responsibility:** The precision engine. Given a `Expr\Variable` node and the surrounding method's AST, infer the variable's class FQN using **intra-procedural, flow-insensitive** analysis. This is the documented, bounded subset of symbol resolution that catches the common 80% without a full type system.

Resolution sources (in priority order), all *within the same method/closure scope*:

1. **Parameter type hints** — `public function show(User $user)` → `$user : App\Models\User`.
2. **`@var` docblocks** — `/** @var User $user */`.
3. **Direct construction** — `$user = new User();`.
4. **Static model entrypoints** — assignment from `User::find()`, `User::query()`, `User::where(...)->first()`, `User::firstOrFail()`, `User::create(...)` → `$user : User`. (Method returns are mapped by a static table of model-returning Eloquent methods.)
5. **Relationship traversal** — if `$user : User` and `User` declares `posts(): HasMany` returning `Post`, then `$user->posts` (a collection) and `$post` in `foreach ($user->posts as $post)` resolve to `Post`. (Relationship return types read from the relation method's return type hint or the `hasMany(Post::class)` first argument.)
6. **`DB::table('users')`** — not a model, but a *direct table binding*; the resolver returns a `TableBinding('users')` rather than a class.

Output: a small `ScopeSymbolTable` (`variableName → ResolvedType`) built per method. Unresolved variables return `ResolvedType::unknown()`, which the calling visitor handles via the confidence tiers.

**Explicit non-goals (Phase 1):** no cross-method flow, no container resolution, no interface/trait method origin tracing, no property-type propagation across calls. These limitations are *intentional* and feed the WARNING tier and the future calibration roadmap; they are documented in `README` under "Known limitations" so users understand why some references are MEDIUM rather than HIGH.

---

### 3.9 `ColumnTokenMatcher`

**Namespace:** `SchemaGuard\Scanning\ColumnTokenMatcher`
**Responsibility:** Two pure functions that govern false-positive suppression.

```php
final class ColumnTokenMatcher
{
    /** Common English words that frequently collide with non-DB identifiers. */
    private const COMMON = ['id','name','email','phone','type','status','title','date','time',
        'value','data','code','key','user','order','active','count','price','address','state'];

    /** A column name is "rare" → low collision risk → safe to weight more heavily. */
    public function rarity(string $column): Rarity
    {
        if (in_array($column, self::COMMON, true)) return Rarity::COMMON;
        if (str_contains($column, '_') || strlen($column) >= 12) return Rarity::RARE; // e.g. stripe_customer_id
        return Rarity::MODERATE;
    }

    /** Map rarity → the confidence assigned to an UNRESOLVED reference. */
    public function confidenceForUnresolved(string $column): Confidence
    {
        return match ($this->rarity($column)) {
            Rarity::RARE     => Confidence::MEDIUM,  // rarely a coincidence
            Rarity::MODERATE => Confidence::MEDIUM,
            Rarity::COMMON   => Confidence::LOW,     // could easily be an unrelated array key/var
        };
    }

    /** Whole-word, SQL-boundary-aware match for raw SQL strings. */
    public function matchesInSql(string $sql, string $token): bool
    {
        // \b is insufficient because `user_id` would match `id`; require SQL-significant boundaries.
        $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($token, '/') . '(?![A-Za-z0-9_])/';
        return (bool) preg_match($pattern, $sql);
    }
}
```

The rarity heuristic is the formalization of the strategy's core risk concern: a column literally named `phone` is far more likely to collide with an unrelated `'phone' => ...` array key than a column named `phone_verified_at`. The matcher never *creates* a verdict; it only sets the confidence that the `PolicyEngine` later thresholds.

---

### 3.10 `DependencyGraph`

**Namespace:** `SchemaGuard\Graph\DependencyGraph`
**Responsibility:** An in-memory directed graph (adjacency list) of typed nodes, with reachability queries. No external graph library — associative arrays are sufficient and keep the dependency surface minimal.

```php
final class DependencyGraph
{
    /** @var array<string,GraphNode> nodeId → node */
    private array $nodes = [];
    /** @var array<string,string[]> nodeId → outgoing neighbor ids */
    private array $edges = [];

    public function addNode(GraphNode $n): void { $this->nodes[$n->id] ??= $n; $this->edges[$n->id] ??= []; }
    public function addEdge(string $from, string $to): void { $this->edges[$from][] = $to; /* dedupe */ }

    /** BFS from a column node; returns every reachable node = the blast radius. */
    public function reachableFrom(string $nodeId): array { /* iterative BFS */ }

    /** Ordered paths from a column to any node of type ROUTE or API_RESOURCE = "exposed" impact. */
    public function exposedPaths(string $columnNodeId): ImpactPath[] { /* DFS, collect to exposed sinks */ }
}
```

**Node ID scheme (stable, human-readable):**
- Column: `column:users.phone`
- Table: `table:users`
- Model: `model:App\Models\User`
- Resource: `resource:App\Http\Resources\UserResource`
- Controller action: `action:App\Http\Controllers\UserController@show`
- Route: `route:GET:/api/users/{user}`

`GraphNode` (value object): `id`, `NodeType $type`, `string $label`, `?SourceLocation $location`.

### 3.11 `DependencyGraphBuilder`

**Namespace:** `SchemaGuard\Graph\DependencyGraphBuilder`
**Responsibility:** Assemble the graph from the model map, the collected `Usage[]`, and the `RouteBinding[]`. Edge construction:

```
column → table              (from ModelTableMap column declarations)
model  → table              (maps_to)
column → model              (for each MODEL_SCHEMA / ELOQUENT_QUERY usage in a model)
resource → model            (associated-model inference)
column → resource           (for each API_RESOURCE usage)
action → controller         (trivial)
controller → model          (controller uses model)
route  → action             (from RouteVisitor bindings)
```

The builder yields, per changed column, a list of `ImpactPath`s such as:
`users.phone → App\Models\User → UserController@show → GET /api/users/{user}`
These paths are what the `ConsoleReporter` prints under each finding so the developer sees *exactly* what breaks, satisfying the strategy's "show the affected files/endpoints, not just a yes/no" requirement.

The graph also produces a boolean **`reachesExposedSurface`** per column (any path ending at a `ROUTE` or `API_RESOURCE` node), which the `PolicyEngine` uses as a severity escalator.

---

### 3.12 `PolicyEngine`

**Namespace:** `SchemaGuard\Policy\PolicyEngine`
**Responsibility:** The deterministic verdict function. Pure: `(events, usages, graph, config) → PolicyResult`. No I/O.

```php
final class PolicyEngine
{
    public function __construct(private readonly PolicyConfiguration $config) {}

    public function evaluate(array $events, array $usages, DependencyGraph $graph): PolicyResult
    {
        $findings = [];
        foreach ($events as $event) {
            $relevant   = $this->usagesFor($event, $usages);           // Usage[] touching this event's symbol
            $maxConf    = $this->peakConfidence($relevant);            // Confidence
            $exposed    = $this->reachesExposed($event, $graph);      // bool
            $severity   = $this->severityFor($event, $maxConf, $exposed); // Severity
            $severity   = $this->config->applyOverrides($event, $severity); // enforced/ignored/custom rules
            $findings[] = new EventFinding($event, $relevant, $severity,
                              $graph->exposedPaths($this->columnNodeId($event)));
        }
        return new PolicyResult($findings, $this->aggregate($findings));
    }
}
```

#### 3.12.1 The default decision matrix (deterministic)

`severityFor(event, maxConfidence, exposed)`:

| `ChangeType` | `DEFINITIVE` / `HIGH` usage | `MEDIUM` / `LOW` usage | No usage |
|---|---|---|---|
| `COLUMN_DROPPED` | **BLOCK** | WARNING | SAFE |
| `COLUMN_RENAMED` | **BLOCK** | WARNING | SAFE |
| `TABLE_DROPPED` | **BLOCK** | WARNING | SAFE |
| `COLUMN_TYPE_CHANGED` | **WARNING** | WARNING | SAFE |

Rationale (straight from the strategy): *dropping a used column = BLOCK; changing the type of a used column = WARNING; additive/non-destructive = SAFE.* Type change is never an automatic BLOCK because a widening change (e.g. `VARCHAR(100)`→`VARCHAR(255)`) is frequently safe; it always warrants human eyes, hence WARNING.

#### 3.12.2 Severity escalation by exposure

If `exposed === true` (the column reaches an API resource or route) **and** the matrix produced `WARNING`, the engine may escalate to `BLOCK` *only when* `config.escalate_exposed_to_block === true` (default `false` in Phase 1 to honor "block only high-confidence"). This is the single configurable severity lever; everything else in the matrix is fixed.

#### 3.12.3 Config overrides (`PolicyConfiguration::applyOverrides`)

Applied after the matrix, in this order:
1. **Ignored symbol** (`ignore.columns` / `ignore.tables`) → force `SAFE`.
2. **Enforced symbol** (`enforce.columns` / `enforce.tables`) → force `BLOCK` regardless of usage (protects sacred columns like `users.id`).
3. **Per-type mode** (`policy.modes.column_dropped = block|warn|off`) → clamps the matrix output (e.g. `warn` downgrades a BLOCK to WARNING; `off` forces SAFE).
4. **Custom rule mapping** (`custom_rules[]`) → an exact `(change_type, table, column) → severity` override, highest precedence.

#### 3.12.4 `PolicyResult` & `EventFinding`

`EventFinding` (readonly): `SchemaChangeEvent $event`, `Usage[] $usages`, `Severity $severity`, `ImpactPath[] $paths`.
`PolicyResult` (readonly): `EventFinding[] $findings`, `Severity $overall` (the **max** severity across findings, where `BLOCK > WARNING > SAFE`), plus `int $blockCount`, `int $warningCount`, `int $safeCount`, and `string[] $diagnostics` (parse failures, dynamic-arg notices).

---

### 3.13 `ConsoleReporter`

**Namespace:** `SchemaGuard\Output\ConsoleReporter`
**Responsibility:** Render a `PolicyResult` to the terminal using Symfony Console components. Full visual spec in §7. Supports `console` (default) and `json` (`--format=json`) output. For JSON, it emits a stable machine schema (see §7.4) and renders **only** JSON (no decorative output) so CI can parse stdout cleanly.

```php
final class ConsoleReporter
{
    public function render(OutputInterface $out, PolicyResult $result, AnalysisRequest $req): void { /* §7 */ }
    public function renderFatal(OutputInterface $out, \Throwable $e): void { /* red error block */ }
}
```

### 3.14 `ExitCodeResolver`

**Namespace:** `SchemaGuard\Output\ExitCodeResolver`
**Responsibility:** Map `(PolicyResult, strict, config)` → process exit code per §7.3.

```php
final class ExitCodeResolver
{
    public function __construct(private readonly PolicyConfiguration $config) {}

    public function resolve(PolicyResult $r, bool $strict): int
    {
        if ($r->overall === Severity::BLOCK) return 1;
        if ($r->overall === Severity::WARNING) {
            if ($strict || $this->config->treatWarningsAsFailure()) return 1;
            return $this->config->warningExitCode();   // 0 (default, "warn mode") or 2 (strict CI)
        }
        return 0;
    }
}
```

---

## 4. Step-by-Step Algorithmic Workflows & Pseudo-Code

### 4.1 Parsing migration classes & capturing `dropColumn` / `renameColumn` / drops / type-change

This is the dispatch logic inside `SchemaCallVisitor::handleBlueprintCall()` and the `Schema::` handling in `enterNode()`. It works identically for **anonymous** and **named** migration classes because both route their operations through the same `up()` method body — the visitor keys on method/closure scope and the `Blueprint` variable, never on the class declaration form.

```
ALGORITHM ParseMigration(filePath):
    ast ← PHPParser.parse(read(filePath))            # throws Error → caller returns [] (degrade)
    decorate(ast) with NameResolver + ParentConnectingVisitor
    visitor ← SchemaCallVisitor(filePath)
    traverse(ast, visitor)
    return visitor.events

# --- inside the visitor, on each node entered (only while insideUp == true) ---

ON StaticCall S where classOf(S) resolves-to Schema facade:
    m ← S.methodName
    IF m ∈ {table, create}:
        currentTable ← literalString(S.args[0])              # 'users'  (null if dynamic)
        blueprintVar ← S.args[1].params[0].name              # e.g. 'table' or 't'
    ELSE IF m ∈ {drop, dropIfExists}:
        t ← literalString(S.args[0])
        IF t ≠ null: EMIT SchemaChangeEvent(TABLE_DROPPED, table=t, loc=locOf(S))

ON MethodCall C where receiverIsBlueprint(C, blueprintVar):
    m ← C.methodName
    SWITCH m:
        CASE 'dropColumn':
            FOR each col IN extractColumns(C.args):          # handles String_ and Array_ forms
                IF col ≠ null:
                    EMIT SchemaChangeEvent(COLUMN_DROPPED, table=currentTable, column=col, loc=locOf(C))
                ELSE:
                    EMIT indeterminate(COLUMN_DROPPED, table=currentTable, reason='dynamic column name')
        CASE 'dropColumns':                                  # alias some codebases use
            (same as dropColumn)
        CASE 'renameColumn':
            from ← literalString(C.args[0]); to ← literalString(C.args[1])
            IF from ≠ null:
                EMIT SchemaChangeEvent(COLUMN_RENAMED, table=currentTable, column=from, renamedTo=to, loc=locOf(C))
        CASE m ∈ COLUMN_TYPE_METHODS  (string,integer,bigInteger,text,boolean,decimal,date,timestamp,json,...):
            # Only a type change if THIS column-definition chain is terminated by ->change()
            IF chainHasChangeModifier(C):
                col ← literalString(C.args[0])
                IF col ≠ null:
                    EMIT SchemaChangeEvent(COLUMN_TYPE_CHANGED, table=currentTable, column=col,
                                           newType=m, loc=locOf(C))

# extractColumns: a dropColumn arg may be String_('phone') OR Array_(['a','b'])
FUNCTION extractColumns(args):
    a0 ← args[0].value
    IF a0 is String_:  return [a0.value]
    IF a0 is Array_:   return [ item.value for item in a0.items if item.value is String_ ]
    return [null]                                            # dynamic → indeterminate

# chainHasChangeModifier: walk OUTWARD via parent links from the column-type call,
# following ->nullable()->default()->change() fluent chains, until a ->change() MethodCall is found
# whose innermost receiver is this same column definition. Distinguishes a fresh column add
# (no ->change()) from a type change on an existing column (->change()).
FUNCTION chainHasChangeModifier(node):
    cursor ← node
    WHILE parent(cursor) is MethodCall AND parent(cursor).var === cursor:
        cursor ← parent(cursor)
        IF cursor.methodName == 'change': return true
    return false
```

**Worked example — anonymous migration:**

```php
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');                 // → COLUMN_DROPPED(users.phone)
            $table->renameColumn('full_name', 'name');   // → COLUMN_RENAMED(users.full_name → name)
            $table->string('email', 320)->change();      // → COLUMN_TYPE_CHANGED(users.email, newType=string)
        });
        Schema::dropIfExists('legacy_sessions');         // → TABLE_DROPPED(legacy_sessions)
    }
    public function down(): void { /* ignored: insideUp == false here */ }
};
```

Four events emitted; nothing from `down()`.

### 4.2 Differentiating a real breaking change from a false positive (column name vs generic variable / array key)

This is the algorithm that earns the product's trust. The principle: **a string equal to a column name is not evidence; a string in a position that semantically binds it to the target table is evidence.** We compute, for every candidate match, a `Confidence`, and only `DEFINITIVE`/`HIGH` confidence on a destructive event yields `BLOCK`.

```
ALGORITHM ClassifyCandidate(node, token, targetTable, file):
    # node is the AST node where `token` (a column name) literally appears.

    # ---------- TIER 1: DEFINITIVE — structural binding to the target table ----------
    IF node is inside a MODEL class whose resolvedTable == targetTable
       AND node is in a binding position (fillable/guarded/casts keys/appends/hidden/
                                          accessor-or-mutator method name):
        return Confidence.DEFINITIVE

    IF node is the column arg of a builder method (where/select/orderBy/...)
       AND receiverRoot(node) provably binds to targetTable:        # via LocalTypeResolver
        return Confidence.DEFINITIVE

    IF node is `$this->{token}` inside a JsonResource whose associated model maps to targetTable:
        return Confidence.DEFINITIVE

    # ---------- TIER 2: HIGH — strong but not conclusive ----------
    IF node is `$var->{token}` AND typeOf($var) == a model mapping to targetTable:
        return Confidence.DEFINITIVE                                  # (resolved attribute access)
    IF node is a validation-rule key in a controller/FormRequest for targetTable's resource:
        return Confidence.HIGH
    IF node is a whole-word match inside a raw-SQL string (§3.9 matchesInSql):
        return Confidence.HIGH
    IF node is a modern-accessor method name AND token NOT corroborated by casts/fillable:
        return Confidence.HIGH

    # ---------- TIER 3 / 4: MEDIUM / LOW — name appears but binding is UNPROVEN ----------
    IF node is `$var->{token}` AND typeOf($var) == UNKNOWN:
        return ColumnTokenMatcher.confidenceForUnresolved(token)      # MEDIUM if rare, LOW if common
    IF node is a builder column arg AND receiverRoot == UNKNOWN:
        return ColumnTokenMatcher.confidenceForUnresolved(token)

    # ---------- TIER 5: NOT A USAGE — reject ----------
    IF node is a bare array key NOT inside a model/resource/validation context:   # config array, view data
        return REJECT
    IF node is a local variable name / function param unrelated to any model:
        return REJECT
    IF node is a string inside a `trans()`/`__()`/route()/view() call:
        return REJECT

    return REJECT
```

**The four canonical confusions, resolved:**

```php
// (1) REAL — definitive: bound to the User model whose table is `users`.
class User extends Model {
    protected $fillable = ['phone'];                 // DEFINITIVE: fillable in a users-bound model
}

// (2) REAL — definitive: receiver provably the users table.
User::where('phone', $n)->first();                   // DEFINITIVE: StaticCall on User → table users
DB::table('users')->select('phone');                 // DEFINITIVE: literal table binding

// (3) FALSE POSITIVE — rejected: unrelated array key, no model/resource/validation context.
$shippingForm = ['phone' => $input, 'zip' => $z];    // REJECT: bare array key in plain code

// (4) AMBIGUOUS — MEDIUM (rare) / LOW (common): unresolved receiver.
$row->phone;                                         // $row type unknown → MEDIUM/LOW, becomes WARNING not BLOCK
```

The net effect: case (3) never affects the verdict; case (4) produces a non-blocking WARNING the developer can dismiss; only cases (1) and (2) — provable bindings — can BLOCK a merge. This is precisely the "block only high-confidence, warn on ambiguity" posture the strategy mandates.

### 4.3 End-to-end run (the full pipeline, as pseudo-code)

```
ALGORITHM CheckCommand():
    request ← AnalysisRequest.from(options, config)

    migrationFiles ← MigrationDiscovery.resolve(request)
    events         ← MigrationParser.parseMany(migrationFiles)
    IF events empty: report SAFE; EXIT 0

    index   ← CodebaseIndexer.index(request.scanPaths)        # parse every app *.php once
    targets ← SymbolTargetSet.from(events)                    # {tables}, {table.column}

    modelMap ← {}                                             # PASS 1: register models → tables
    FOR file IN index where parsed:
        EloquentModelVisitor.register(file, modelMap)

    usages ← []                                               # PASS 2: collect usages
    FOR file IN index where parsed:
        FOR visitor IN [EloquentModel(usage), EloquentUsage, ApiResource, Controller, Route, RawSql]:
            usages += visitor.run(file, targets, modelMap, typeResolver, tokenMatcher)
    usages ← dedupe(usages)                                   # keep max confidence per (symbol, location)

    graph  ← DependencyGraphBuilder.build(modelMap, usages, routeBindings)
    result ← PolicyEngine.evaluate(events, usages, graph)

    ConsoleReporter.render(result)
    EXIT ExitCodeResolver.resolve(result, request.strict)
```

---

## 5. Robust Edge Cases & Framework Quirks Handling

Each subsection states the quirk, the failure it would cause if naïvely handled, and the **explicit strategy** SchemaGuard must implement.

### 5.1 Eloquent dynamic attributes (explicit vs. dynamic properties)

**Quirk.** Eloquent attributes are mostly *implicit*: a column `phone` is accessible as `$user->phone` with no declaration anywhere. Conversely, an *accessor* like `fullName(): Attribute` or `getFullNameAttribute()` creates a virtual attribute `full_name` that has **no backing column**. A naïve scanner would (a) miss real columns that are never declared, and (b) falsely treat computed accessors as columns.

**Strategy.**
1. **Implicit attributes are caught at the *usage* site, not the declaration site.** Because `$user->phone` is resolved through `LocalTypeResolver` to the `User`→`users` binding, SchemaGuard detects the column there even though `phone` is declared nowhere. It does **not** require the column to appear in `$fillable`.
2. **Computed accessors are corroborated.** A method name that maps to a candidate column is `Confidence::HIGH`, then *downgraded to evidence-of-computed-attribute (REJECT as a column reference)* if the accessor body references *other* columns and the candidate column appears in **no** structural list (`$fillable`/`$casts`/`$guarded`) and in **no** resolved query. Concretely: `fullName()` reading `$this->first_name . $this->last_name` produces detections for `first_name`/`last_name` (real) and **not** for `full_name` (virtual).
3. **`$appends` disambiguates.** A name present in `$appends` is explicitly a *computed* attribute appended to serialization → treated as **virtual**, never a column drop target. (This prevents flagging an `$appends = ['full_name']` as a missing column.)

### 5.2 Raw database queries (`DB::select()`, `whereRaw()`, …)

**Quirk.** Raw SQL is an opaque string from PHP's perspective. The column may appear with table qualification (`users.phone`), aliased (`phone AS contact`), inside functions (`COALESCE(phone, '')`), or embedded in unrelated words (`telephone`).

**Strategy.**
1. **Word-boundary, SQL-aware matching only** (`ColumnTokenMatcher::matchesInSql`): the token must be flanked by non-identifier characters, so `phone` does **not** match `telephone` or `phone_book` substrings.
2. **Confidence capped at `HIGH`, never `DEFINITIVE`.** Because SchemaGuard does not run a SQL grammar in Phase 1, it cannot prove the token is a column of the *target* table (it might be a column of a joined table sharing the name). A whole-word hit adjacent to SQL syntax is strong (`HIGH`) but the cap means a raw-SQL-only signal on a `COLUMN_TYPE_CHANGED` stays WARNING, and on a `COLUMN_DROPPED` reaches BLOCK only because drops are inherently high-severity.
3. **Table qualification boosts confidence.** If the raw string contains `users.phone` (the qualified form matching the target table), confidence is `HIGH` with a note; if only the bare column appears, `MEDIUM`.
4. **Always emit a diagnostic for raw matches** so the developer knows the finding came from un-parsed SQL and can verify manually — transparency about analysis limits is a trust requirement, not optional.
5. **Unscannable raw input** (e.g. `DB::select($queryFromVariable)`) where the SQL itself is dynamic → emit an `indeterminate` diagnostic ("raw query not statically analyzable"), never a silent pass.

### 5.3 Backward-compatible (non-destructive) vs. destructive migrations

**Quirk.** Most migrations are additive and safe (`addColumn`, `addIndex`, new tables). Some are destructive only on rollback (`dropColumn` inside `down()`). A naïve tool that flags every `dropColumn` token would block safe additive PRs constantly.

**Strategy.**
1. **`down()` is ignored entirely.** Destructive calls in `down()` are the *rollback of an additive change* and represent no forward risk. The `insideUp` scope guard (§3.5.3) enforces this.
2. **Only the four destructive forward operations create events.** Additive operations (`$table->string('newcol')` *without* `->change()`, `addColumn`, `index`, `foreign`, table creation) produce **no event** → contribute SAFE. This is why `chainHasChangeModifier` is essential: it is the sole discriminator between "add a column" (safe) and "change a column's type" (warn).
3. **Expand-then-contract is respected across the analyzed set.** If the analyzed migration set contains *both* a `renameColumn('old','new')` **and** evidence the codebase already references `new` (the contract step of a safe two-phase rename), the rename of `old` is still flagged if `old` is referenced — SchemaGuard reports the *current* breakage truthfully and lets the policy/config (`ignore.columns`) acknowledge an intentional migration. Phase 1 does not attempt to *infer* that a rename is "safe because a compatibility shim exists"; it reports the literal reference state and relies on `ignore`/`warn` config for intentional transitions. (Auto-detecting zero-downtime patterns is explicitly a post-Phase-1 calibration feature.)
4. **A `dropColumn` immediately re-added in the same `up()`** (rare, but seen in column-reorder hacks) is detected by pairing drop+add of the same `table.column` within one `up()` body and downgraded to SAFE with a diagnostic.

### 5.4 Additional framework quirks (must-handle)

- **Custom `Blueprint` variable names.** Never assume `$table`; read the closure parameter name (`resolveBlueprintVar`). A migration using `function (Blueprint $t)` must work identically.
- **Facade aliasing / FQ vs imported.** `Schema::`, `\Schema::`, `\Illuminate\Support\Facades\Schema::`, and a `use Illuminate\Support\Facades\Schema;` import must all resolve to the same facade via the `NameResolver` visitor — match on the resolved FQ name, not the written token.
- **Models not extending `Model` directly.** `extends Authenticatable`, `extends Pivot`, or a project base class (`extends BaseModel`) are all Eloquent models. Detect by walking `extends` to a known Eloquent base **or** by structural signal (presence of `$table`/`$fillable`/`$casts`/relationship calls). Resolve through one level of project base class by name.
- **`HasFactory`/trait noise.** Traits used by models are not models; do not treat trait files as table-bound.
- **Multiple tables in one migration.** Sibling `Schema::table()` closures must not leak table context into each other — reset `currentTable`/`blueprintVar` on closure exit (`leaveNode`).
- **`$casts` enum/relationship casts.** A cast value like `PhoneType::class` or `'datetime'` is irrelevant; only the cast **key** is the column.
- **Soft deletes / timestamps.** `deleted_at`, `created_at`, `updated_at` are real columns; a drop of these is a real event. They are *not* special-cased away (a drop of `deleted_at` genuinely breaks soft-delete queries).
- **Parse failures.** Any file PHP-Parser cannot parse (syntax newer than the host grammar, intentionally broken fixtures) degrades to `ParsedFile::failed` and increments the diagnostics counter; the run continues and the final report states "N files could not be analyzed."

---

## 6. Package Configuration Schema (`config/schemaguard.php`)

The published configuration. Every key is documented inline. `PolicyConfiguration::fromArray()` validates this and throws `ConfigurationException` on unknown enum values (e.g. an invalid mode), so misconfiguration fails fast rather than silently mis-behaving.

```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Scan Paths
    |--------------------------------------------------------------------------
    | Directories (relative to the application base path) that SchemaGuard
    | parses when hunting for column/table usages. Keep this tight: every
    | extra path is more files to parse and more chances for coincidental
    | name collisions. The defaults cover the surfaces SchemaGuard understands.
    */
    'scan_paths' => [
        'app',
        'routes',
        'database/factories',   // factories reference columns heavily
        'database/seeders',
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Paths
    |--------------------------------------------------------------------------
    | Where migration files live. Used by MigrationDiscovery. Multiple paths
    | are supported for apps that split migrations per-domain or per-package.
    */
    'migration_paths' => [
        'database/migrations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore Paths (glob patterns)
    |--------------------------------------------------------------------------
    | Files matching any pattern are NEVER parsed. Use this to silence
    | generated code, vendored stubs, or large data files that produce noise.
    | Patterns are matched against the absolute path with fnmatch().
    */
    'ignore_paths' => [
        '*/vendor/*',
        '*/storage/*',
        '*/bootstrap/cache/*',
        '*/tests/*',            // remove if you want test code scanned too
        '*/database/migrations/*', // never scan migrations AS usage sources
    ],

    /*
    |--------------------------------------------------------------------------
    | Policy Modes (per change type)
    |--------------------------------------------------------------------------
    | Clamps the verdict for each event type. This is the master "warning mode"
    | switch the rollout strategy calls for: ship with everything on `warn`,
    | then promote the high-confidence types to `block` once the team trusts it.
    |
    |   'block' → may produce BLOCK (exit 1) when evidence is high-confidence
    |   'warn'  → highest possible verdict is WARNING (never blocks the merge)
    |   'off'   → this change type is ignored entirely (always SAFE)
    */
    'policy' => [
        'modes' => [
            'column_dropped'     => 'block',
            'column_renamed'     => 'block',
            'table_dropped'      => 'block',
            'column_type_changed'=> 'warn',   // type changes warn by default, per design
        ],

        /*
        | When true, a WARNING-level change that reaches an EXPOSED surface
        | (an API Resource or an HTTP route) is escalated to BLOCK. Off by
        | default to honor "block only on high-confidence" during early adoption.
        */
        'escalate_exposed_to_block' => false,

        /*
        | Confidence floor for a BLOCK. Evidence below this never blocks; it
        | downgrades to WARNING. DEFINITIVE > HIGH > MEDIUM > LOW.
        | Lower this only if you accept more false positives.
        */
        'block_confidence_floor' => 'high',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enforced Symbols (always BLOCK)
    |--------------------------------------------------------------------------
    | "Sacred" tables/columns whose destruction is ALWAYS a BLOCK, regardless
    | of whether SchemaGuard can prove a usage. Use for primary keys, billing
    | tables, audit logs — anything where a drop is categorically unacceptable.
    | Columns are written as `table.column`.
    */
    'enforce' => [
        'tables'  => [
            'users',
            'subscriptions',
        ],
        'columns' => [
            'users.id',
            'users.email',
            'subscriptions.stripe_id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Symbols (always SAFE)
    |--------------------------------------------------------------------------
    | Tables/columns SchemaGuard must NOT flag. Use when you are intentionally
    | dropping/renaming something and have already migrated all references
    | (e.g. the contract step of a planned zero-downtime rename).
    */
    'ignore' => [
        'tables'  => [
            'legacy_import_staging',
        ],
        'columns' => [
            'users.deprecated_nickname',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Rules (highest precedence)
    |--------------------------------------------------------------------------
    | Exact (change_type, table, column) → severity overrides. These beat the
    | default matrix AND the per-type modes. `column` may be null to match any
    | column of the table. Severity ∈ safe|warning|block.
    |
    | Example: never let anyone change the type of money columns silently.
    */
    'custom_rules' => [
        // ['change_type' => 'column_type_changed', 'table' => 'invoices', 'column' => 'amount_cents', 'severity' => 'block'],
        // ['change_type' => 'column_dropped',      'table' => 'audit_logs', 'column' => null,         'severity' => 'block'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Builder Column Methods
    |--------------------------------------------------------------------------
    | The query-builder methods whose string arguments are treated as column
    | references, with the argument position(s) that hold columns. Extend this
    | if your team uses custom macros that accept a column name.
    |   'all'   → every string/array-string argument is a column
    |   [0]     → only the first argument
    */
    'builder_column_methods' => [
        'where' => [0], 'orWhere' => [0], 'whereNot' => [0], 'whereColumn' => [0, 1],
        'whereIn' => [0], 'whereNotIn' => [0], 'whereNull' => [0], 'whereNotNull' => [0],
        'whereBetween' => [0], 'having' => [0], 'orderBy' => [0], 'orderByDesc' => [0],
        'groupBy' => 'all', 'select' => 'all', 'addSelect' => 'all',
        'pluck' => [0, 1], 'value' => [0], 'increment' => [0], 'decrement' => [0],
        'sum' => [0], 'avg' => [0], 'max' => [0], 'min' => [0],
        'firstWhere' => [0], 'oldest' => [0], 'latest' => [0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Common Column Names (rarity heuristic)
    |--------------------------------------------------------------------------
    | Column names treated as COMMON English words → unresolved references to
    | them are weighted LOW (likely coincidental), reducing false positives.
    | Domain-specific names not in this list get MEDIUM weight when unresolved.
    */
    'common_column_names' => [
        'id', 'name', 'email', 'phone', 'type', 'status', 'title', 'date', 'time',
        'value', 'data', 'code', 'key', 'user', 'order', 'active', 'count', 'price',
        'address', 'state', 'country', 'city', 'description', 'image', 'url', 'slug',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exit Codes (CI/CD)
    |--------------------------------------------------------------------------
    | How verdicts map to the process exit code. See §7.3 of the blueprint.
    |   BLOCK  → always 1 (not configurable)
    |   SAFE   → always 0 (not configurable)
    |   WARNING→ `warning_exit_code` (0 = don't fail CI on warnings [default],
    |            2 = soft-fail). `treat_warnings_as_failure=true` forces 1.
    */
    'exit_codes' => [
        'warning_exit_code'         => 0,
        'treat_warnings_as_failure' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | AST Parse Cache
    |--------------------------------------------------------------------------
    | Caches parsed ASTs keyed by file path + mtime + content hash to speed up
    | repeated local runs. Disabled automatically with the --no-cache flag.
    */
    'cache' => [
        'enabled' => true,
        'path'    => storage_path('framework/cache/schemaguard'),
    ],

];
```

---

## 7. CLI Output Design & CI/CD Compliance

### 7.1 Visual specification (console format)

The reporter uses Symfony Console primitives only (`SymfonyStyle`, `Table`, output formatter tags `<info>`, `<comment>`, `<error>`, plus custom styles registered on the `OutputFormatter`). Color is suppressed automatically when the output is not a TTY or `--no-ansi` is passed (Symfony handles this), so logs stay clean in CI.

**Custom formatter styles** (registered in `ConsoleReporter::render`):

```php
$out->getFormatter()->setStyle('block',   new OutputFormatterStyle('white', 'red',    ['bold']));
$out->getFormatter()->setStyle('warn',    new OutputFormatterStyle('black', 'yellow', ['bold']));
$out->getFormatter()->setStyle('safe',    new OutputFormatterStyle('black', 'green',  ['bold']));
$out->getFormatter()->setStyle('path',    new OutputFormatterStyle('cyan'));
```

**Layout, top to bottom:**

1. **Header band.**
   ```
   SchemaGuard — Deployment Firewall for Database Changes
   Analyzed 3 migration(s) · 142 source files · 2 file(s) unparseable
   ```

2. **Per-event finding blocks**, ordered by severity (BLOCK first, then WARNING, then SAFE-with-note). Each finding renders a color-coded status tag, the event, and an impact `Table`:
   ```
   ┌─ <block> BLOCK </block> ── COLUMN_DROPPED ────────────────────────────────┐
   │ users.phone   (database/migrations/2024_06_01_000000_drop_phone.php:14)   │
   └────────────────────────────────────────────────────────────────────────┘

     Impacted usages (3):
     ┌────────────┬──────────────────────────────────────────┬──────┬────────────┐
     │ Surface    │ Location                                  │ Line │ Confidence │
     ├────────────┼──────────────────────────────────────────┼──────┼────────────┤
     │ Model      │ app/Models/User.php  ($fillable)          │  18  │ DEFINITIVE │
     │ Resource   │ app/Http/Resources/UserResource.php       │  24  │ DEFINITIVE │
     │ Raw SQL    │ app/Reports/ContactReport.php (selectRaw) │  51  │ HIGH       │
     └────────────┴──────────────────────────────────────────┴──────┴────────────┘

     Blast radius:
       users.phone → App\Models\User → UserController@show → GET /api/users/{user}
   ```

3. **WARNING blocks** use the `<warn>` tag and the same table; e.g. a `COLUMN_TYPE_CHANGED` or an ambiguous (MEDIUM/LOW) reference:
   ```
   ┌─ <warn> WARN </warn> ── COLUMN_TYPE_CHANGED ──────────────────────────────┐
   │ users.email → string   (…/2024_06_04_000000_change_email_type.php:16)     │
   └────────────────────────────────────────────────────────────────────────┘
     1 usage (MEDIUM): app/Services/Mailer.php:32  ($row->email — unresolved receiver)
   ```

4. **Diagnostics section** (only if non-empty): parse failures, dynamic schema operations, raw-SQL caveats.
   ```
   Diagnostics:
     • 2 file(s) could not be parsed and were skipped (coverage incomplete).
     • Dynamic column name in …/2024_07_..php:21 — manual review advised.
   ```

5. **Summary footer band** — the single-glance verdict, color-coded to the overall severity:
   ```
   ──────────────────────────────────────────────────────────────────────────
    <block> RESULT: BLOCK </block>   1 blocking · 1 warning · 0 safe
    Merge should be stopped: a used column is being dropped.
   ──────────────────────────────────────────────────────────────────────────
   ```
   For a clean run:
   ```
    <safe> RESULT: SAFE </safe>   0 blocking · 0 warning · 3 analyzed
    No destructive change affects known usages. Cleared to deploy.
   ```

**Tone of the verdict line** matches the strategy's honesty rule — it says "a used column is being dropped," never "this will definitely break production." SchemaGuard reports evidence, not prophecy.

### 7.2 Verdict-to-color mapping

| Overall severity | Footer tag | Color | Meaning |
|---|---|---|---|
| `BLOCK` | `RESULT: BLOCK` | white-on-red | High-confidence destructive change against a live usage. |
| `WARNING` | `RESULT: WARNING` | black-on-yellow | Ambiguous or type-change risk; human review advised. |
| `SAFE` | `RESULT: SAFE` | black-on-green | No destructive change affects a known usage. |

### 7.3 Exit codes (CI/CD contract)

The exit code is the machine-facing product. It is computed by `ExitCodeResolver` (§3.14) and is **stable and documented** so pipelines can branch on it.

| Condition | Exit code | Notes |
|---|---|---|
| Overall `SAFE` (or no events) | **`0`** | Always. Pipeline proceeds. |
| Overall `BLOCK` | **`1`** | Always. Hard fail; merge gate should stop here. |
| Overall `WARNING`, `treat_warnings_as_failure=false`, not `--strict` | `warning_exit_code` (**default `0`**) | "Warn mode": surfaces warnings without failing CI. |
| Overall `WARNING`, `warning_exit_code=2` | **`2`** | "Soft-fail mode": distinguishable non-zero for pipelines that branch on it. |
| Overall `WARNING`, `--strict` **or** `treat_warnings_as_failure=true` | **`1`** | Warnings are promoted to hard failures. |
| Fatal (bad config / missing scan root) | **`1`** | Environment/config failure is a hard fail. |

This honors the requested mapping (`0` = safe, `1` = blocked/breaking, `2` = warnings-depending-on-config) while accommodating the reality that most CI systems treat *any* non-zero as failure — so warnings default to `0` until a team explicitly opts into stricter exit behavior. The rollout story is: start with warnings at exit `0`, graduate to `--strict` once trust is established.

**Example GitHub Actions step (documentation artifact, ships in README — not built in Phase 1):**

```yaml
- name: SchemaGuard
  run: php artisan schemaguard:check --diff --base=origin/main --strict
```
Here `--strict` makes both BLOCK and WARNING fail the job (exit 1), giving the team a hard gate once they trust the tool.

### 7.4 JSON output schema (`--format=json`)

For programmatic consumers, `--format=json` emits **only** this object on stdout (no banner, no color), enabling the future PR-gate and any custom CI parsing:

```json
{
  "schema_version": "1.0",
  "overall": "BLOCK",
  "counts": { "block": 1, "warning": 1, "safe": 0 },
  "exit_code": 1,
  "analyzed": { "migrations": 3, "source_files": 142, "unparsed_files": 2 },
  "findings": [
    {
      "change_type": "COLUMN_DROPPED",
      "table": "users",
      "column": "phone",
      "renamed_to": null,
      "new_type": null,
      "severity": "BLOCK",
      "migration": { "file": "database/migrations/2024_06_01_000000_drop_phone.php", "line": 14 },
      "usages": [
        { "surface": "MODEL_SCHEMA", "file": "app/Models/User.php", "line": 18, "confidence": "DEFINITIVE", "detail": "$fillable" },
        { "surface": "API_RESOURCE", "file": "app/Http/Resources/UserResource.php", "line": 24, "confidence": "DEFINITIVE", "detail": "$this->phone" },
        { "surface": "RAW_SQL", "file": "app/Reports/ContactReport.php", "line": 51, "confidence": "HIGH", "detail": "selectRaw" }
      ],
      "impact_paths": [
        "users.phone → App\\Models\\User → UserController@show → GET /api/users/{user}"
      ]
    }
  ],
  "diagnostics": [
    "2 file(s) could not be parsed and were skipped.",
    "Dynamic column name in database/migrations/2024_07_..php:21 — manual review advised."
  ]
}
```

---

## Appendix A — Value Object Catalog

All are `final readonly` classes under `SchemaGuard\ValueObjects\`. Construction is via named constructors where it aids readability.

```php
enum ChangeType: string {
    case COLUMN_DROPPED      = 'column_dropped';
    case COLUMN_RENAMED      = 'column_renamed';
    case TABLE_DROPPED       = 'table_dropped';
    case COLUMN_TYPE_CHANGED = 'column_type_changed';
}

enum Confidence: int {           // ordered: higher int = stronger evidence
    case LOW        = 1;
    case MEDIUM     = 2;
    case HIGH       = 3;
    case DEFINITIVE = 4;
    public function atLeast(self $floor): bool { return $this->value >= $floor->value; }
}

enum Severity: int {             // ordered for max() aggregation
    case SAFE    = 0;
    case WARNING = 1;
    case BLOCK   = 2;
}

enum SurfaceType: string {
    case MODEL_SCHEMA  = 'model_schema';   // fillable/casts/accessor — definitive column declaration
    case ELOQUENT_QUERY= 'eloquent_query'; // where/select/attribute access
    case API_RESOURCE  = 'api_resource';   // exposed through a JsonResource
    case CONTROLLER    = 'controller';     // validation / request input / action query
    case RELATION      = 'relation';       // relationship foreign/local key
    case RAW_SQL       = 'raw_sql';        // matched inside a raw SQL string
}

final readonly class TableReference {
    public function __construct(public string $table) {}
    public function id(): string { return "table:{$this->table}"; }
}

final readonly class ColumnReference {
    public function __construct(public string $table, public string $column) {}
    public function id(): string { return "column:{$this->table}.{$this->column}"; }
    public function equals(self $o): bool { return $this->table === $o->table && $this->column === $o->column; }
}

final readonly class SourceLocation {
    public function __construct(public string $file, public int $line, public ?int $column = null) {}
    public static function fromNode(string $file, \PhpParser\Node $n): self {
        return new self($file, $n->getStartLine());
    }
}

final readonly class Usage {
    public function __construct(
        public ColumnReference|TableReference $symbol,
        public SurfaceType $surface,
        public Confidence  $confidence,
        public SourceLocation $location,
        public string $detail = '',          // e.g. '$fillable', 'where()', 'selectRaw'
    ) {}
}

final readonly class SchemaChangeEvent {
    public function __construct(
        public ChangeType $type,
        public ?TableReference  $table,
        public ?ColumnReference $column,
        public SourceLocation $location,
        public ?string $renamedTo = null,    // COLUMN_RENAMED only
        public ?string $newType   = null,    // COLUMN_TYPE_CHANGED only
        public bool    $indeterminate = false, // dynamic arg → manual review
    ) {}

    public static function columnDropped(ColumnReference $c, SourceLocation $l): self { /* … */ }
    public static function columnRenamed(ColumnReference $c, string $to, SourceLocation $l): self { /* … */ }
    public static function tableDropped(TableReference $t, SourceLocation $l): self { /* … */ }
    public static function columnTypeChanged(ColumnReference $c, string $newType, SourceLocation $l): self { /* … */ }
    public static function indeterminate(ChangeType $type, ?TableReference $t, string $reason, SourceLocation $l): self { /* … */ }
}
```

---

## Appendix B — The Confidence Model (Formal)

Confidence is the spine of the false-positive defense. The mapping from evidence to confidence is fixed (below); the mapping from confidence to verdict lives in the `PolicyEngine` matrix (§3.12.1) and is clamped by config.

```
EVIDENCE                                                              → CONFIDENCE
column in $fillable/$guarded/$casts-key/$appends of a table-bound model → DEFINITIVE
legacy/explicit accessor or mutator name in a table-bound model         → DEFINITIVE
$var->col  where typeof($var) resolves to the model of the target table → DEFINITIVE
Model::where('col') / DB::table('users')->where('col') (resolved root)  → DEFINITIVE
$this->col inside a JsonResource whose model maps to the target table   → DEFINITIVE
modern Attribute-style accessor name, NOT corroborated by casts/fillable→ HIGH
validation rule key in a controller/FormRequest for the target resource → HIGH
whole-word raw-SQL match adjacent to SQL syntax                         → HIGH
qualified raw-SQL match `table.col`                                     → HIGH
$var->col / builder col with UNRESOLVED receiver, RARE column name      → MEDIUM
$request->input('col') style request access                            → MEDIUM
$var->col / builder col with UNRESOLVED receiver, COMMON column name    → LOW
bare array key outside model/resource/validation context               → (rejected)
local variable / param / trans()/route()/view() string                 → (rejected)
```

`block_confidence_floor` (config, default `high`) is the threshold: a destructive event blocks only if its peak usage confidence `>= floor`. Lowering the floor trades false negatives for false positives; the default deliberately favors precision.

---

## Appendix C — Testing Strategy & Fixtures

Testing uses **Orchestra Testbench** (the canonical way to boot a minimal Laravel app around a package). Fixtures under `tests/Fixtures/` are **parsed, never executed**.

**Coverage requirements:**

1. **`MigrationParserTest`** — one test per event type × (anonymous class, named class) × (single column, array of columns, dynamic arg). Assert exact `SchemaChangeEvent` output, including that `down()` operations produce nothing and dynamic args produce `indeterminate`.
2. **Visitor unit tests** — feed a hand-written AST fixture, assert the exact `Usage[]` with expected `Confidence`. Must include the four canonical confusions (§4.2) and assert case (3) yields **zero** usages.
3. **`LocalTypeResolverTest`** — each resolution source (param hint, docblock, `new`, static entrypoint, relation traversal, `DB::table`) resolves correctly; unrelated variables resolve to `unknown`.
4. **`PolicyEngineTest`** — exhaustively walk the decision matrix (4 change types × 3 confidence bands), then each config override layer (ignore, enforce, mode clamp, custom rule) in isolation and in precedence-conflict combinations.
5. **`ExitCodeResolverTest`** — every row of the §7.3 table.
6. **`CheckCommandTest` (Feature, E2E)** — run `schemaguard:check --migrations=…` against the fixture mini-app; assert (a) the rendered output contains the expected status band, (b) the JSON output matches the §7.4 schema, and (c) the **process exit code** is correct. This is the test that proves the whole pipeline composes.

**Golden-file approach:** the E2E test compares `--format=json` output against a committed golden JSON, making regressions in any layer visible as a single diff.

---

## Appendix D — Build Order / Milestones

The agent must build bottom-up so each layer is testable before the next depends on it.

1. **Scaffolding** — `composer.json`, `SchemaGuardServiceProvider`, empty `config/schemaguard.php`, `tests/TestCase.php` (Testbench), CI-less local PHPUnit green on an empty suite.
2. **Value objects + enums** (Appendix A) — pure, fully unit-tested.
3. **`MigrationParser` + `SchemaCallVisitor`** — §3.5, §4.1. Green against migration fixtures. *At this milestone the tool can already detect events.*
4. **`CodebaseIndexer` + `ParsedFile`** — §3.6. Parses fixtures, degrades on broken files.
5. **`LocalTypeResolver` + `ColumnTokenMatcher`** — §3.8, §3.9. Unit-tested in isolation (the precision core).
6. **Usage visitors** — §3.7.1→3.7.6, one at a time, each with its own unit test, starting with `EloquentModelVisitor` (it builds `ModelTableMap` that the others need).
7. **`StaticAnalysisScanner`** — §3.7. Two-pass orchestration; green against the fixture app producing the expected `Usage[]`.
8. **`DependencyGraph` + builder** — §3.10, §3.11. Impact paths assertable.
9. **`PolicyEngine` + `PolicyConfiguration`** — §3.12. The matrix + overrides, fully tested.
10. **`ConsoleReporter` + `ExitCodeResolver`** — §3.13, §3.14, §7.
11. **`AnalysisPipeline` + `CheckCommand`** — §3.2, §3.3. Wire everything; the E2E `CheckCommandTest` goes green.
12. **`MigrationDiscovery` strategies** — §3.4. `--diff` and `--migrations` modes.
13. **README + docs** — the painful, concrete "this migration would have broken production" example the go-to-market depends on.

---

## Appendix E — Glossary

| Term | Meaning in this blueprint |
|---|---|
| **AST** | Abstract Syntax Tree — the structured, node-based representation of PHP source produced by `nikic/php-parser`. The substrate for all analysis. |
| **Schema change event** | A typed `SchemaChangeEvent` extracted from a migration (one of the four Phase-1 types). |
| **Usage** | A detected, located reference to a target column/table in application code, carrying a `Confidence`. |
| **Surface** | The kind of code a usage lives in (model schema, query, API resource, controller, relation, raw SQL). |
| **Confidence** | How strongly the evidence binds a string to the target symbol: DEFINITIVE > HIGH > MEDIUM > LOW. |
| **Verdict / Severity** | The per-event and overall decision: SAFE < WARNING < BLOCK. |
| **Blast radius / impact path** | The chain from a changed column to an exposed surface, via the dependency graph. |
| **Exposed surface** | A node reachable from a column that represents external exposure — an API resource or an HTTP route. |
| **Definitive binding** | Evidence that conclusively ties a string to the target table (model-bound declaration or resolved query receiver). |
| **Two-pass scan** | Register models→tables first, then collect usages — so every visitor knows table bindings regardless of file order. |

---

*End of `TECHNICAL_BLUEPRINT.md` — Phase 1. This document is complete and implementation-ready: every layer, class signature, algorithm, edge case, configuration key, and exit code required to build `schemaguard/laravel` from scratch is specified above.*
