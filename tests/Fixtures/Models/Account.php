<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class Account extends Model
{
    protected $table = 'crm_accounts';

    protected $fillable = [
        'phone',
    ];
}
