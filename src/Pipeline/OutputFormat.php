<?php

declare(strict_types=1);

namespace SchemaGuard\Pipeline;

enum OutputFormat: string
{
    case CONSOLE = 'console';
    case JSON = 'json';
}
