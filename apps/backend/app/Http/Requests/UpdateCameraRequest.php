<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCameraRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:80'],
            'role' => ['sometimes', 'in:entry_tripwire,counter_window,density,overview'],
            'source_type' => ['sometimes', 'in:rtsp,webcam'],
            'rtsp_url' => ['sometimes', 'string', 'max:400'],
            'tripwire_json' => ['sometimes', 'nullable', 'array'],
            'tripwire_json.line' => ['required_with:tripwire_json', 'array', 'size:2'],
            'tripwire_json.line.*' => ['array', 'size:2'],
            'tripwire_json.line.*.*' => ['integer', 'min:0'],
            'tripwire_json.direction' => ['required_with:tripwire_json', 'in:up,down,left,right'],
            'status' => ['sometimes', 'in:active,degraded,disabled'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        /** @var array<string, mixed> $validated */
        $validated = parent::validated($key, $default);

        if (array_key_exists('tripwire_json', $validated) && $validated['tripwire_json'] === []) {
            $validated['tripwire_json'] = null;
        }

        return $validated;
    }
}
