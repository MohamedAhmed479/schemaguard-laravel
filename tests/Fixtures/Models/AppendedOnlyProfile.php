<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

final class AppendedOnlyProfile extends Model
{
    protected $table = 'profiles';

    protected $appends = [
        'display_name',
    ];

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => 'computed',
        );
    }
}
