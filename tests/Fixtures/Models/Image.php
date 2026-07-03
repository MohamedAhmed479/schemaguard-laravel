<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class Image extends Model
{
    public function imageable()
    {
        return $this->morphTo();
    }
}
