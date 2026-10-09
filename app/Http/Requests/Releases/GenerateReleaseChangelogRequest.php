<?php

namespace App\Http\Requests\Releases;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateReleaseChangelogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller enforces project membership and management permissions.
        return true;
    }

    public function rules(): array
    {
        return [
            // BCP 47 language tags: en, pl, de, pt-BR, zh-Hant-TW, etc.
            'language' => ['sometimes', 'required', 'string', 'max:35', 'regex:/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})*\z/i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('language'))) {
            $this->merge(['language' => trim($this->input('language'))]);
        }
    }
}
