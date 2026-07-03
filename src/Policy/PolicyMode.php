<?php

declare(strict_types=1);

namespace SchemaGuard\Policy;

enum PolicyMode: string
{
    case BLOCK = 'block';
    case WARN = 'warn';
    case OFF = 'off';
}
