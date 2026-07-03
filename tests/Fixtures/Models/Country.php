<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class Country extends Model
{
    public function posts()
    {
        return $this->hasManyThrough(Post::class, User::class, 'country_id', 'user_id', 'id', 'id');
    }

    public function profile()
    {
        return $this->hasOneThrough(Profile::class, User::class, 'country_id', 'user_id', 'id', 'id');
    }
}
