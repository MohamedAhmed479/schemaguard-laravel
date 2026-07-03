<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use SchemaGuard\Tests\Fixtures\Models\FilenameOnly;
use SchemaGuard\Tests\Fixtures\Models\User;

function typeResolverFixture(?User $user, int $nonModelId): void
{
    /** @var User $docUser */
    $docUser = $user;
    $newUser = new User();
    $foundUser = User::find(1);
    $queryUser = User::query();
    $chainedUser = User::query()->where('phone', '555-0100');
    $whereUser = User::where('phone', '555-0100');
    $createdUser = User::create(['phone' => '555-0101']);
    $firstUser = User::first();
    $firstOrFailUser = User::firstOrFail();
    $query = DB::table('users');
    $chainedQuery = DB::table('users')->where('phone', '555-0102')->orderBy('phone');
    $notModel = FilenameOnly::find(1);
    $unknownNew = new FilenameOnly();
    $unknown = $input;
}
