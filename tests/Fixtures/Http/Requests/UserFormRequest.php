<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Fixtures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UserFormRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
        ];
    }
}
