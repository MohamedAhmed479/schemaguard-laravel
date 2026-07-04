# SchemaGuard Demo Assets

This demo shows the core SchemaGuard scenario: a Laravel migration drops `users.email` while Laravel application code still references that column.

Expected behavior: SchemaGuard reports `RESULT: BLOCK` and returns exit code `1` before deployment.

The displayed terminal output in `blocking-a-used-column-drop.svg` comes from a real local run against a temporary Laravel application using `schemaguard/laravel v0.1.0`. Local absolute paths and terminal spacing were redacted or shortened for readability; the command, migration action, affected symbol, verdict, and exit code are preserved.

Command used:

```bash
php artisan schemaguard:check \
  --migrations=database/migrations/2026_07_04_000000_drop_email_from_users.php \
  --path=app \
  --no-ansi
```

Migration snippet:

```php
$table->dropColumn('email');
```

README embed example:

```md
![SchemaGuard blocks a used column drop](docs/demo/blocking-a-used-column-drop.svg)
```
