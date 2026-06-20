<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['sometimes', 'in:shadow,live,disabled'],
            'festival_mode' => ['sometimes', 'boolean'],
            'default_quota' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
