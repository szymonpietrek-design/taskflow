<?php

namespace App\Http\Requests\Reports;

use App\Models\Project;
use App\Models\Report;
use App\Support\Reports\ReportAccess;
use Illuminate\Foundation\Http\FormRequest;

final class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->route('report') instanceof Report
            && app(ReportAccess::class)->canEdit($this->user(), $this->route('report'));
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
