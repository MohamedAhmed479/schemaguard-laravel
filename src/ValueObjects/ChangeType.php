<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

enum ChangeType: string
{
    case COLUMN_DROPPED = 'column_dropped';
    case COLUMN_RENAMED = 'column_renamed';
    case TABLE_DROPPED = 'table_dropped';
    case COLUMN_TYPE_CHANGED = 'column_type_changed';
}
