<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\Http\Controllers;

use Illuminate\Http\Request;
use SchemaGuard\Tests\Fixtures\Models\User;

final class DedupeController
{
    public function sameLine(Request $request): void
    {
        User::where('phone', $request->input('phone'))->first();
    }

    public function distinctLocations(): void
    {
        User::where('phone', '555-0100')->first();
        User::where('phone', '555-0101')->first();
    }
}
