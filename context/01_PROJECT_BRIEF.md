# Project Brief

Read this for product identity and product philosophy. Skip it when making a narrow code change and you already understand SchemaGuard's purpose.

## Identity

- Product name: SchemaGuard
- Package identity: `schemaguard/laravel`
- Positioning: Deployment firewall for database schema changes in Laravel applications.

## Core Product Behavior

SchemaGuard should eventually produce a decision, not just a report:

- `SAFE`
- `WARNING`
- `BLOCK`

Phase 1, Phase 2, and Phase 3 currently provide the package foundation, migration extraction layer, AST indexing, and usage discovery core. The full decision engine is Planned — not implemented.

## Destructive Event Model

Status: Implemented and verified.

The enum is implemented. Not every event type is detected yet.

The Phase-1 domain model includes:

- `COLUMN_DROPPED`
- `COLUMN_RENAMED`
- `TABLE_DROPPED`
- `COLUMN_TYPE_CHANGED`

Detection status:

- `COLUMN_DROPPED`: Implemented and verified in the migration parser.
- `COLUMN_RENAMED`: Implemented and verified in the migration parser.
- `TABLE_DROPPED`: Implemented and verified for `Schema::drop` and `Schema::dropIfExists`.
- `COLUMN_TYPE_CHANGED`: Implemented and verified through AST `->change()` detection.

## Product Philosophy

- Decision, not just report.
- Precision over coverage.
- High-confidence evidence may block.
- Ambiguity warns rather than pretending certainty.
- Never execute host migrations or host models during analysis.
- False negatives in destructive migration detection must not disappear silently.
- False positives are also harmful and must be covered by regression tests.

## Out of Scope Future Areas

Planned — not implemented:

- GitHub App
- SaaS dashboard
- Multi-repository support
- ML calibration
- Non-Laravel parsers
