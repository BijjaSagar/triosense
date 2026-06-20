<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CutoffOverrideRequest extends FormRequest
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
            'action' => ['required', 'in:force_open,force_close,set_cutoff'],
            'cutoff_position' => ['required_if:action,set_cutoff', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
