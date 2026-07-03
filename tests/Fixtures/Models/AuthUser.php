<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class AuthUser extends Authenticatable
{
    protected $fillable = [
        'phone',
    ];
}
