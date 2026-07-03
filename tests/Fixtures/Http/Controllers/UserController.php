<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\Http\Controllers;

use Illuminate\Http\Request;
use SchemaGuard\Tests\Fixtures\Models\User;

final class UserController
{
    public function store(Request $request): void
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $phone = $request->input('phone');
        $request->phone;

        User::where('phone', $phone)->first();
        User::select('phone')->first();
        User::orderBy('phone')->first();
    }

    public function show(User $user): string
    {
        return $user->phone;
    }
}
