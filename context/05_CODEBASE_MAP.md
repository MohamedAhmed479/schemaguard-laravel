# Codebase Map

Read this when locating files for a task. Skip it if the exact file is already known and the task is trivial.

## Package Foundation

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `composer.json` | Composer package identity, dependencies, autoload, Laravel provider discovery, scripts. | Package setup, dependency, autoload, or provider questions. | Keep package name `schemaguard/laravel`; keep PHP-Parser dependency for later phases even while Phase 2 is tokenizer-based. |
| `README.md` | Minimal installation, command, and config publish usage. | User-facing package docs change. | Do not claim full analysis is wired until Phase 5. |
| `LICENSE.md` | MIT license. | License/package hygiene tasks. | Do not modify casually. |
| `.gitattributes` | Composer export-ignore rules. | Package distribution hygiene. | Keep tests, `phpunit.xml.dist`, and `.gitattributes` excluded. |

## Configuration

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `config/schemaguard.php` | Publishable package config surface. | Config publishing, future policy/scanner config, or Testbench issues. | Some keys are future-facing but must not be described as active behavior until consumed by code. |

## Console

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Console/Commands/CheckCommand.php` | Phase 1 smoke-test command. | Command registration/output changes. | Must remain `schemaguard:check`; no Phase 5 options until pipeline work begins. |

## Exceptions

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Exceptions/SchemaGuardException.php` | Stable root exception. | Exception hierarchy changes. | Keep as root package exception. |
| `src/Exceptions/MigrationParseException.php` | Parser failure wrapper. | Migration parser resilience or diagnostics work. | Parser should catch and expose diagnostics instead of crashing full runs. |

## Migrations

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/Migrations/MigrationDiscovery.php` | Resolves migration files for Phase 2. | Migration file selection or path behavior. | Supports explicit and pending path strategies only; Git diff is Phase 5 and throws not-supported. |
| `src/Migrations/MigrationParser.php` | Token-based Phase 2 parser. | Any migration parsing task. | Uses `token_get_all(..., TOKEN_PARSE)`; preserves `parseMany` and `parseFile`; diagnostics must remain public. |

## Value Objects

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `src/ValueObjects/ChangeType.php` | Destructive event enum. | Event model changes. | `COLUMN_TYPE_CHANGED` exists, but detection is Planned — not implemented until Phase 3B. |
| `src/ValueObjects/TableReference.php` | Immutable table identity. | Reference equality/id tasks. | Stable `id()` format matters for later graph/policy phases. |
| `src/ValueObjects/ColumnReference.php` | Immutable table+column identity. | Column event or equality tasks. | `equals()` is semantic; do not weaken. |
| `src/ValueObjects/SourceLocation.php` | File/line/optional column source metadata. | Diagnostics or event location changes. | No PHP-Parser dependency in Phase 2. |
| `src/ValueObjects/SchemaChangeEvent.php` | Event payload and named constructors. | Parser event output changes. | Keep fields needed by later phases: type, table, column, location, `renamedTo`, `newType`, `indeterminate`, reason. |

## Tests

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `tests/TestCase.php` | Testbench bootstrap. | Provider/config test issues. | Must register `SchemaGuardServiceProvider`. |
| `tests/Feature/CheckCommandTest.php` | Phase 1 command smoke test. | Command behavior changes. | Proves command registration, banner, and exit code. |
| `tests/Unit/Migrations/MigrationParserTest.php` | Phase 2 parser regression suite. | Parser changes. | Must cover destructive operations, `down()`, dynamic args, comments/strings, diagnostics, malformed source. |
| `tests/Unit/Migrations/MigrationDiscoveryTest.php` | Phase 2 discovery suite. | Discovery changes. | Must preserve sorting, `.php` filtering, explicit validation, and Git diff rejection. |
| `tests/Unit/ValueObjects/ReferenceTest.php` | Value object identity/equality tests. | Reference object changes. | Keep ids stable. |

## Fixtures

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `tests/Fixtures/migrations/` | Parsed-but-never-executed migration fixtures. | Parser/discovery test changes. | Fixtures should remain valid PHP except intentional malformed fixture elsewhere. |
| `tests/Fixtures/malformed/broken_migration.fixture` | Malformed parser resilience fixture. | Parser diagnostics changes. | Must never crash the test suite. |

## Specifications

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `IMPLEMENTATION_ROADMAP.md` | Canonical phase plan and Definition of Done. | Any phase work. | Build top-to-bottom; do not skip phase gates. |
| `TECHNICAL_BLUEPRINT.md` | Detailed architecture specification. | Design or future phase implementation. | Contains planned AST examples that may not exist yet in code. |

## Context System

| Path | Purpose | Read When | Invariants / Warnings |
| --- | --- | --- | --- |
| `AGENTS.md` | Minimal root pointer for agents. | Starting work from repository root. | Keep short; do not duplicate full context. |
| `context/` | Agent operating memory. | Every multi-step task. | If context conflicts with source/tests, update context. |

## Read Only When Needed

- Do not load every fixture unless changing parser behavior.
- Do not read the full blueprint for small docs/package hygiene changes; use roadmap plus targeted context first.
- Do not inspect `vendor/` unless debugging dependency behavior.
