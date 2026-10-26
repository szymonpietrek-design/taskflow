<?php

namespace App\Http\Requests\Settings;

use App\Support\I18n\LanguageCatalog;
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
                Rule::in(array_column(app(LanguageCatalog::class)->options(), 'value')),
            ],
        ];
    }
}
