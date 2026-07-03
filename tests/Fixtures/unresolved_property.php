<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures;

function unresolvedPropertyFixture($row): array
{
    return [
        $row->phone,
        $row->phone_verified_at,
    ];
}
