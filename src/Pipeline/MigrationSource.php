<?php

declare(strict_types=1);

namespace SchemaGuard\Pipeline;

enum MigrationSource: string
{
    case PENDING = 'pending';
    case EXPLICIT = 'explicit';
    case GIT_DIFF = 'git_diff';
}
