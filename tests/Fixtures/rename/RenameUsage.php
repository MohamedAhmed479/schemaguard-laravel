<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\rename;

use SchemaGuard\Tests\Fixtures\Models\User;

final class RenameUsage
{
    public function findByLegacyName(string $name): mixed
    {
        return User::where('full_name', $name)->first();
    }
}
