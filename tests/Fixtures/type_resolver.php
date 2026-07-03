<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use SchemaGuard\Tests\Fixtures\Models\User;

function typeResolverFixture(User $user): void
{
    /** @var User $docUser */
    $docUser = $user;
    $newUser = new User();
    $foundUser = User::find(1);
    $queryUser = User::query();
    $whereUser = User::where('phone', '555-0100');
    $createdUser = User::create(['phone' => '555-0101']);
    $firstUser = User::first();
    $firstOrFailUser = User::firstOrFail();
    $query = DB::table('users');
    $unknown = $input;
}
