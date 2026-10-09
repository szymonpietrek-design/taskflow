<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FilterTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        // Check visibility before validating project-specific status IDs.
        return Gate::forUser($this->user())->allows('viewAny', [Task::class, $project]);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->query('q'))) {
            $this->merge(['q' => trim($this->query('q'))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status_id' => [
                'nullable', 'integer',
                Rule::exists('task_statuses', 'id')->where('project_id', $project->id),
            ],
            'priority' => [
                'nullable', 'integer',
                Rule::in(array_map(fn (TaskPriority $priority): int => $priority->value, TaskPriority::cases())),
            ],
            // "unassigned" is a useful filter without requiring a user record.
            'assignee_id' => ['nullable', 'regex:/^(unassigned|[1-9][0-9]*)$/'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'priority', 'due_date', 'title'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
