<?php

namespace App\Http\Requests\Reports;

use App\Models\Project;
use App\Models\Report;
use App\Support\Reports\ReportAccess;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateReportContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $report = $this->route('report');

        return $project instanceof Project && $report instanceof Report
            && app(ReportAccess::class)->canEdit($this->user(), $report);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:50000'],
            'version' => ['required', 'string', 'size:64'],
        ];
    }
}
