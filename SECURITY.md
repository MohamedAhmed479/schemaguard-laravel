# Security Policy

SchemaGuard is a static-analysis package for Laravel schema-change safety. It should not execute host migrations, application models, controllers, routes, resources, or database queries during analysis.

## Reporting a Security Issue

Please do not open a public issue with exploit details, secrets, credentials, tokens, private schema details, or proof-of-concept payloads.

GitHub private vulnerability reporting has not been confirmed as enabled for this repository. Maintainer action required: replace this placeholder with a private security contact or enable GitHub private vulnerability reporting.

Until a private channel is listed, open a public GitHub issue with only a non-sensitive title such as `Security contact requested` and ask the maintainer for a private reporting channel. Do not include technical exploit details in that public issue.

## What to Include Privately

When a private channel is available, include:

- A concise description of the issue.
- Affected SchemaGuard version.
- Minimal reproduction steps.
- Whether the issue affects console output, JSON output, file parsing, cache behavior, or package installation.
- Any safe, redacted logs needed to understand the risk.

## Supported Versions

The first public release is `v0.1.0`. Until a broader support policy is published, report suspected security issues against the latest public release.
