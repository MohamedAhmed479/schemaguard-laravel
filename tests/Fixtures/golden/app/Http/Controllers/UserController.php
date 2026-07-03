<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\golden\app\Http\Controllers;

use Illuminate\Support\Facades\DB;
use SchemaGuard\Tests\Fixtures\golden\app\Models\User;

final class UserController
{
    public function show(User $user): string
    {
        DB::select($this->dynamicSql());

        return (string) $user->phone;
    }

    private function dynamicSql(): string
    {
        return 'SELECT phone FROM users';
    }
}
