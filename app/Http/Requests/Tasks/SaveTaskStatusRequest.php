<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskStatusCategory;
use App\Models\Project;
use App\Models\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        $status = $this->route('status');
        abort_if($status instanceof TaskStatus && $status->project_id !== $project->id, 404);

        return $this->user()?->can('update', $project) === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');
        $status = $this->route('status');

        $nameRule = Rule::unique('task_statuses', 'name')->where('project_id', $project->id);
        if ($status instanceof TaskStatus) {
            $nameRule->ignore($status->id);
        }

        return [
            'name' => ['required', 'string', 'max:80', $nameRule],
            'category' => ['required', Rule::in(array_column(TaskStatusCategory::cases(), 'value'))],
        ];
    }
}
