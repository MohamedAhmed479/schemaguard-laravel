<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use SchemaGuard\Tests\Fixtures\Models\User;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'phone' => $this->phone,
        ];
    }
}
