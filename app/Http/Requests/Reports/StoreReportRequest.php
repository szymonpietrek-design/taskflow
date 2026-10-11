<?php

namespace App\Http\Requests\Reports;

use App\Enums\ReportSource;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $project->archived_at === null
            && $this->user()?->can('update', $project) === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:160'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'sources' => ['required', 'array', 'min:1', 'max:4'],
            'sources.*' => ['required', 'string', Rule::in(array_map(static fn (ReportSource $source): string => $source->value, ReportSource::cases())), 'distinct'],
        ];
    }
}
