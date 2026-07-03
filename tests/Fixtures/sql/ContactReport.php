<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\sql;

use Illuminate\Support\Facades\DB;

final class ContactReport
{
    public function usedPhone(): array
    {
        return DB::select('SELECT users.phone FROM users WHERE users.phone IS NOT NULL');
    }

    public function decoy(): array
    {
        return DB::select('SELECT telephone, microphone, phone_number FROM directory');
    }

    public function dynamic(string $sql): array
    {
        return DB::select($sql);
    }
}
