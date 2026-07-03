# Project Brief

Read this for product identity and product philosophy. Skip it when making a narrow code change and you already understand SchemaGuard's purpose.

## Identity

- Product name: SchemaGuard
- Package identity: `schemaguard/laravel`
- Positioning: Deployment firewall for database schema changes in Laravel applications.

## Core Product Behavior

SchemaGuard produces a decision, not just a report:

- `SAFE`
- `WARNING`
- `BLOCK`

Phase 1 through Phase 6 provide the package foundation, migration extraction, AST indexing, usage discovery, graph/policy decision engine, CLI reporting, and robustness hardening. The Phase-1 product implementation is complete and verified.

## Destructive Event Model

Status: Implemented and verified.

All Phase-1 destructive event types are represented and detected within the supported Laravel migration scope.

- `COLUMN_DROPPED`
- `COLUMN_RENAMED`
- `TABLE_DROPPED`
- `COLUMN_TYPE_CHANGED`

## Product Philosophy

- Decision, not just report.
- Precision over coverage.
- High-confidence evidence may block.
- Ambiguity warns rather than pretending certainty.
- Never execute host migrations or host models during analysis.
- False negatives in destructive migration detection must not disappear silently.
- False positives are also harmful and must be covered by regression tests.

## Out of Scope Future Areas

Planned - not implemented:

- GitHub App
- SaaS dashboard
- Multi-repository support
- ML calibration
- Non-Laravel parsers
