# SchemaGuard for Laravel

SchemaGuard is a deployment firewall for database schema changes in Laravel applications.

It statically analyzes pending, explicit, or Git-diff migration files, maps destructive schema changes to Laravel code usage, and returns a CI-friendly decision: `SAFE`, `WARNING`, or `BLOCK`.

## Installation

```bash
composer require schemaguard/laravel
```

Publish the configuration when you need to customize paths, policy modes, ignores, enforced symbols, or cache location:

```bash
php artisan vendor:publish --tag=schemaguard-config
```

## Basic Usage

Analyze pending migrations and the configured application paths:

```bash
php artisan schemaguard:check
```

Analyze explicit migrations and source paths:

```bash
php artisan schemaguard:check \
  --migrations=database/migrations/2026_07_03_000000_drop_phone_from_users.php \
  --path=app
```

Analyze migrations added or modified relative to a Git base:

```bash
php artisan schemaguard:check --diff --base=origin/main
```

Emit JSON for automation:

```bash
php artisan schemaguard:check --diff --base=origin/main --format=json
```

## Caught Before Merge

If a migration drops a used column:

```php
Schema::table('users', function (Blueprint $table): void {
    $table->dropColumn('phone');
});
```

and application code still reads `users.phone`, SchemaGuard reports `BLOCK` and exits `1`:

```text
BLOCK COLUMN_DROPPED users.phone
RESULT: BLOCK
```

## Decisions and Exit Codes

| Result | Meaning | Default exit code |
| --- | --- | ---: |
| `SAFE` | No matching usage evidence was found, or the change is explicitly ignored/neutralized. | `0` |
| `WARNING` | Risk is ambiguous or intentionally non-blocking, such as used type changes by default. | configured, default `0` |
| `BLOCK` | High-confidence usage evidence or enforced policy says the change should not ship. | `1` |

Use `--strict` to make warnings fail CI:

```bash
php artisan schemaguard:check --diff --base=origin/main --strict
```

## GitHub Actions

```yaml
- name: SchemaGuard
  run: php artisan schemaguard:check --diff --base=origin/main --strict
```

## JSON Contract

JSON mode writes only one JSON document to stdout. The payload includes:

```text
schema_version
overall
counts
exit_code
analyzed
findings
diagnostics
```

`findings` include the migration event, target table/column, severity, usage evidence, confidence, source locations, impact paths, and neutralization/indeterminate flags where applicable.

## Known Limitations

- Type inference is conservative and intra-procedural.
- Raw SQL support uses token-boundary matching for static SQL strings, not a full SQL grammar.
- Raw SQL evidence is capped at `HIGH` confidence and never becomes `DEFINITIVE`.
- Dynamic SQL is reported as an indeterminate diagnostic instead of guessed.
- SchemaGuard never executes host migrations, models, controllers, resources, routes, or database queries.

## License

SchemaGuard is open-sourced software licensed under the MIT license.
