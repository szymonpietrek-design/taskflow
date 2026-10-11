<?php

namespace App\Http\Requests\Reports;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

final class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $project->archived_at === null
            && $this->user()?->can('update', $project) === true;
    }

    public function rules(): array
    {
        return [
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
