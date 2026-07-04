# Release Checklist

Use this checklist before creating a public SchemaGuard release tag. It is a release-process aid, not a replacement for test output.

## Before Tagging

- Confirm the working tree is clean.
- Run the full PHPUnit suite.
- Run the coverage threshold check.
- Run Composer validation.
- Run a fresh Laravel installation test.
- Inspect the Composer archive contents.
- Review `README.md`.
- Verify configuration publishing.
- Verify JSON output.
- Confirm no generated files are staged.
- Review `composer.json`.
- Review changelog or release notes.

## Before Publishing

- Confirm package name availability and ownership manually.
- Confirm GitHub repository visibility and default branch.
- Push tags intentionally.
- Create GitHub release notes.
- Submit package to Packagist or enable auto-update.
- Verify package install from Packagist in a fresh app.

## After Publishing

- Install the released version from Packagist in a fresh app.
- Run package discovery.
- Run `schemaguard:check`.
- Verify configuration publishing.
- Verify JSON output.
- Verify documentation links.

## v0.1.0 Completion Record

- Release tag `v0.1.0` was created and pushed for commit `ee9fbdfdc3beffd358b594e58f99967d331fd100`.
- GitHub Release `SchemaGuard v0.1.0` was published for `MohamedAhmed479/schemaguard-laravel`.
- Packagist package `schemaguard/laravel` detected version `v0.1.0` and is configured for GitHub auto-updates.
- Public install verification passed in a clean Laravel app using `composer require schemaguard/laravel:^0.1 -W` with no path repository.
- Auto-discovery, command registration, config publishing, SAFE/BLOCK/WARNING behavior, JSON output, and strict warning handling were verified from the public package.
