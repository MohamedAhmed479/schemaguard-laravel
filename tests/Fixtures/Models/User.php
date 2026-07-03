<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

final class User extends Model
{
    protected $fillable = [
        'phone',
        'name',
        'active',
    ];

    protected $guarded = [
        'ssn',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];

    protected $visible = [
        'public_phone',
    ];

    protected $dates = [
        'deleted_at',
    ];

    protected $appends = [
        'full_name',
    ];

    public function getPhoneAttribute(?string $value): ?string
    {
        return $value;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = $value;
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->first_name . ' ' . $this->last_name,
        );
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id', 'id', 'id');
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
