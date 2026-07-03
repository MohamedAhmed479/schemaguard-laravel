<?php

declare(strict_types=1);

namespace SchemaGuard\ValueObjects;

enum Rarity: string
{
    case COMMON = 'common';
    case MODERATE = 'moderate';
    case RARE = 'rare';
}
