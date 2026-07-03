<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\golden\app\Models;

use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $fillable = [
        'phone',
    ];
}
