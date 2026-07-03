<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Scan Paths
    |--------------------------------------------------------------------------
    |
    | Directories, relative to the host Laravel application base path, that
    | SchemaGuard will parse when later phases scan for table and column usage.
    | Keep this list tight to reduce parsing cost and coincidental matches.
    |
    */
    'scan_paths' => [
        'app',
        'routes',
        'database/factories',
        'database/seeders',
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Paths
    |--------------------------------------------------------------------------
    |
    | Directories containing Laravel migration files. Multiple paths are
    | supported for applications that split migrations by domain or package.
    |
    */
    'migration_paths' => [
        'database/migrations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore Paths
    |--------------------------------------------------------------------------
    |
    | Glob patterns matched against absolute paths. Matching files are never
    | parsed as usage sources.
    |
    */
    'ignore_paths' => [
        '*/vendor/*',
        '*/storage/*',
        '*/bootstrap/cache/*',
        '*/tests/*',
        '*/database/migrations/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Policy
    |--------------------------------------------------------------------------
    |
    | Per-change-type modes clamp the final verdict. The full policy engine is
    | introduced in a later phase; these keys are present now so published
    | configuration is stable from the first package release.
    |
    | Supported modes: block, warn, off.
    |
    */
    'policy' => [
        'modes' => [
            'column_dropped' => 'block',
            'column_renamed' => 'block',
            'table_dropped' => 'block',
            'column_type_changed' => 'warn',
        ],

        'escalate_exposed_to_block' => false,
        'block_confidence_floor' => 'high',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enforced Symbols
    |--------------------------------------------------------------------------
    |
    | Tables and columns that should always block when destructive changes are
    | detected. Columns are written as table.column.
    |
    */
    'enforce' => [
        'tables' => [
            'users',
            'subscriptions',
        ],
        'columns' => [
            'users.id',
            'users.email',
            'subscriptions.stripe_id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Symbols
    |--------------------------------------------------------------------------
    |
    | Tables and columns that should be ignored by SchemaGuard. Use this for
    | intentionally removed symbols after the team has migrated all references.
    |
    */
    'ignore' => [
        'tables' => [
            'legacy_import_staging',
        ],
        'columns' => [
            'users.deprecated_nickname',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Rules
    |--------------------------------------------------------------------------
    |
    | Exact change_type/table/column severity overrides. Later phases will
    | validate and apply these rules after the default decision matrix.
    |
    */
    'custom_rules' => [
        // ['change_type' => 'column_type_changed', 'table' => 'invoices', 'column' => 'amount_cents', 'severity' => 'block'],
        // ['change_type' => 'column_dropped', 'table' => 'audit_logs', 'column' => null, 'severity' => 'block'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Builder Column Methods
    |--------------------------------------------------------------------------
    |
    | Query-builder methods whose string arguments are treated as column names.
    | The value lists the argument positions that contain columns, or "all" for
    | all string and array-string arguments.
    |
    */
    'builder_column_methods' => [
        'where' => [0],
        'orWhere' => [0],
        'whereNot' => [0],
        'whereColumn' => [0, 1],
        'whereIn' => [0],
        'whereNotIn' => [0],
        'whereNull' => [0],
        'whereNotNull' => [0],
        'whereBetween' => [0],
        'having' => [0],
        'orderBy' => [0],
        'orderByDesc' => [0],
        'groupBy' => 'all',
        'select' => 'all',
        'addSelect' => 'all',
        'pluck' => [0, 1],
        'value' => [0],
        'increment' => [0],
        'decrement' => [0],
        'sum' => [0],
        'avg' => [0],
        'max' => [0],
        'min' => [0],
        'firstWhere' => [0],
        'oldest' => [0],
        'latest' => [0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Common Column Names
    |--------------------------------------------------------------------------
    |
    | Unresolved references to these common names are weighted lower by the
    | confidence model because they often collide with unrelated array keys or
    | request fields.
    |
    */
    'common_column_names' => [
        'id',
        'name',
        'email',
        'phone',
        'type',
        'status',
        'title',
        'date',
        'time',
        'value',
        'data',
        'code',
        'key',
        'user',
        'order',
        'active',
        'count',
        'price',
        'address',
        'state',
        'country',
        'city',
        'description',
        'image',
        'url',
        'slug',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exit Codes
    |--------------------------------------------------------------------------
    |
    | BLOCK and SAFE mappings are fixed by the product contract. WARNING can
    | either stay informational or become a distinct soft-fail/hard-fail signal.
    |
    */
    'exit_codes' => [
        'warning_exit_code' => 0,
        'treat_warnings_as_failure' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | AST Parse Cache
    |--------------------------------------------------------------------------
    |
    | Later phases will cache parsed ASTs under this path. Phase 1 only
    | publishes the stable configuration surface.
    |
    */
    'cache' => [
        'enabled' => true,
        'path' => storage_path('framework/cache/schemaguard'),
    ],

];
