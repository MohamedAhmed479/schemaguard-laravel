<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

final class UserController
{
    public function show(User $user): string
    {
        return $user->phone;
    }

    public function store(Request $request): void
    {
        User::where('phone', $request->input('phone'))->first();
    }
}
