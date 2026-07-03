<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\Models;

final class TableRelationModel
{
    protected $table = 'relation_backed_models';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
