<?php

namespace App\Http\Requests\Settings;

use App\Enums\UiLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocaleUpdateRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => [
                'required',
                'string',
                Rule::enum(UiLocale::class),
            ],
        ];
    }
}
