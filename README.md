# SchemaGuard for Laravel

SchemaGuard is a deployment firewall for database schema changes.

It is designed to help Laravel teams detect high-confidence risky schema changes before they reach production. The Phase 1 package skeleton registers the Laravel service provider, publishes configuration, and exposes the `schemaguard:check` Artisan command. The static analysis engine is added in later phases.

## Installation

```bash
composer require schemaguard/laravel
```

## Usage

```bash
php artisan schemaguard:check
```

Phase 1 output confirms that the package is installed and the command is wired:

```text
SchemaGuard - Deployment Firewall for Database Changes
No analysis wired yet.
```

## Configuration

Publish the configuration file with:

```bash
php artisan vendor:publish --tag=schemaguard-config
```

## License

SchemaGuard is open-sourced software licensed under the MIT license.
