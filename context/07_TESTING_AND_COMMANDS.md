# Testing And Commands

Read this before running validation or declaring a task complete. Skip it only for pure reading tasks with no final correctness claim.

## Environment / Bootstrap

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `composer install` | Fresh checkout or dependency drift. | Dependencies can install from `composer.lock`. | Completes without dependency conflicts. | Yes for dependency/package tasks; otherwise as needed. |
| `composer dump-autoload -o` | After class/file/autoload changes. | Optimized Composer autoload is valid. | Optimized autoload generated. | Yes for new PHP classes. |
| `composer validate --strict` | Package metadata changes or release readiness. | `composer.json` is valid. | `./composer.json is valid`. | Yes for package/dependency changes. |

## Validation

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `git status` | Start and finish of every task. | Worktree awareness. | Review current changes before proceeding. | Yes. |
| `git diff --check` | Start and finish of every task. | No whitespace/conflict-marker issues. | No output, exit 0. | Yes. |

## Phase 1 Commands

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `vendor/bin/phpunit tests/Feature/CheckCommandTest.php` | Command/provider/config changes. | Command registration, config merge, banner, exit 0. | Test passes. | Yes for Phase 1 changes. |
| `vendor/bin/testbench schemaguard:check` | Command integration changes. | Testbench can run the package command. | Prints Deployment Firewall scaffold output. | Yes for command changes. |
| `vendor/bin/testbench vendor:publish --tag=schemaguard-config --force` | Config/provider publishing changes. | Package config publishes in Testbench. | `schemaguard.php` copied. | Yes for config/provider changes. |

## Phase 2 Commands

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `vendor/bin/phpunit --filter MigrationParserTest` | Parser or fixture changes. | Destructive event extraction, diagnostics, false-positive guards, and Phase 3 type changes. | Parser tests pass. | Yes for parser changes. |
| `vendor/bin/phpunit --filter MigrationDiscoveryTest` | Discovery/path behavior changes. | Explicit/pending discovery, sorting, non-PHP filtering, Git diff rejection. | Discovery tests pass. | Yes for discovery changes. |

## Phase 3 Commands

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `vendor/bin/phpunit --filter CodebaseIndexerTest` | AST indexer changes. | Recursive discovery, ignore paths, safe parse failures, resolved names, parent links. | Indexer tests pass. | Yes for indexer changes. |
| `vendor/bin/phpunit --filter EloquentModelVisitorTest` | Model visitor changes. | Model schema, legacy accessor, relation, and computed accessor behavior. | Model visitor tests pass. | Yes for model visitor changes. |
| `vendor/bin/phpunit --filter EloquentUsageVisitorTest` | Eloquent query/property visitor changes. | Static model queries, typed property access, unresolved confidence. | Eloquent usage tests pass. | Yes for Eloquent usage changes. |
| `vendor/bin/phpunit --filter ApiResourceVisitorTest` | Resource visitor changes. | Resource `$this->column` exposure with proven model association and fallback confidence. | Resource tests pass. | Yes for resource visitor changes. |
| `vendor/bin/phpunit --filter ControllerVisitorTest` | Controller/FormRequest visitor changes. | Validation/rules keys are high confidence, request input/property access is medium, and Eloquent query logic is not duplicated. | Controller visitor tests pass. | Yes for controller visitor changes. |
| `vendor/bin/phpunit --filter LocalTypeResolverTest` | Type resolver changes. | Parameter, docblock, `new`, static model entrypoint, `DB::table`, and unknown resolution. | Type resolver tests pass. | Yes for type resolver changes. |
| `vendor/bin/phpunit --filter ColumnTokenMatcherTest` | Token matcher changes. | Rarity confidence and SQL-boundary matching. | Matcher tests pass. | Yes for matcher changes. |
| `vendor/bin/phpunit --filter StaticAnalysisScannerTest` | Scanner coordinator changes. | Two-pass scanner, target scoping, false-positive gate, dedupe, and failed parsed file skipping. | Scanner tests pass. | Yes for scanner changes. |

## Full Suite

| Command | When To Run | What It Proves | Expected Outcome | Mandatory Before Complete |
| --- | --- | --- | --- | --- |
| `vendor/bin/phpunit --testsuite Unit` | Any Phase 2/3 unit change. | All unit regressions remain green. | Unit suite passes. | Yes for Phase 2/3 source changes. |
| `vendor/bin/phpunit` | Any code/test change. | Full current package suite. | All tests pass. | Yes for code changes. |

## Troubleshooting

- Composer/autoload issues: run `composer dump-autoload -o`; verify PSR-4 namespace and file path match.
- Testbench command issues: verify `SchemaGuardServiceProvider` registers commands only when `runningInConsole()` and publishes with tag `schemaguard-config`.
- Malformed fixture failures: parser/indexer should return safe failed results and expose diagnostics where applicable; the full suite must not crash.
- Tests passing but context stale: source code wins; update `context/03_CURRENT_STATE.md`, `context/09_ACTIVE_WORK.md`, and any affected map/decision files.
