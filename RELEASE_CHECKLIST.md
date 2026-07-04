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
