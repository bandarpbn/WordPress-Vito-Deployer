<?php

namespace App\Vito\Plugins\Bandarpbn\WordpressVitoDeployer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkProvisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.domain' => ['required', 'string'],
            'rows.*.server_id' => ['required', 'integer', 'exists:servers,id'],
            'rows.*.title' => ['nullable', 'string'],
            'rows.*.tagline' => ['nullable', 'string'],
            'rows.*.timezone' => ['nullable', 'string'],
            'rows.*.admin_username' => ['nullable', 'string'],
            'rows.*.admin_email' => ['nullable', 'email'],
            'rows.*.admin_password' => ['nullable', 'string'],
            'rows.*.plugins' => ['nullable', 'string'],
            'rows.*.theme' => ['nullable', 'string'],
        ];
    }
}
