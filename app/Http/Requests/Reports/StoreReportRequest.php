<?php

namespace App\Http\Requests\Reports;

use App\Enums\ReportFormat;
use App\Enums\ReportSource;
use App\Models\Project;
use App\Models\User;
use App\Support\Reports\ReportAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $project->archived_at === null
            && app(ReportAccess::class)->canCreate($this->user(), $project);
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
            'contributor_ids' => ['nullable', 'array', 'min:1', 'max:100'],
            'contributor_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'title' => ['nullable', 'string', 'max:160'],
            'format' => ['sometimes', 'required', Rule::enum(ReportFormat::class)],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'sources' => ['required', 'array', 'min:1', 'max:4'],
            'sources.*' => ['required', 'string', Rule::in(array_map(static fn (ReportSource $source): string => $source->value, ReportSource::cases())), 'distinct'],
        ];
    }

    /** Work Report needs actual time entries; never infer hours from task activity. */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $project = $this->route('project');
            $actor = $this->user();
            $requested = $this->input('contributor_ids');
            if ($project instanceof Project && $actor instanceof User) {
                if (! app(ReportAccess::class)->canManageTeam($actor, $project)) {
                    if ($requested !== null && array_map('intval', (array) $requested) !== [$actor->id]) {
                        $validator->errors()->add('contributor_ids', 'You can only report your own work.');
                    }
                } elseif (is_array($requested)) {
                    $eligible = User::query()->whereIn('id', $requested)
                        ->where(fn ($query) => $query
                            ->whereHas('workspaceMemberships', fn ($q) => $q->where('workspace_id', $project->workspace_id)->whereNull('suspended_at')->whereIn('role', [1, 2]))
                            ->orWhereHas('projectMemberships', fn ($q) => $q->where('project_id', $project->id)))
                        ->pluck('id')->all();
                    if (count($eligible) !== count($requested)) {
                        $validator->errors()->add('contributor_ids', 'Select contributors who belong to this project or workspace.');
                    }
                }
            }
            if ($this->input('format') === ReportFormat::WORK_REPORT->value
                && ! in_array(ReportSource::WORKLOGS->value, (array) $this->input('sources', []), true)) {
                $validator->errors()->add('sources', 'Work Report requires Worklogs as a source.');
            }
        }];
    }
}
