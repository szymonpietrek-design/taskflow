<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user() !== null
            && $this->user()->can('create', [Task::class, $project]);
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => ['required', Rule::in(array_column(TaskType::cases(), 'value'))],
            'priority' => ['required', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'status_id' => ['nullable', 'integer', Rule::exists('task_statuses', 'id')->where('project_id', $project->id)],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $project->id)->whereNull('parent_id')],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Project $project */
                $project = $this->route('project');
                $assigneeId = $this->input('assignee_id');

                if ($assigneeId === null || $assigneeId === '' || $validator->errors()->has('assignee_id')) {
                    return;
                }

                $assignee = User::query()->find($assigneeId);

                if ($assignee === null || Gate::forUser($assignee)->denies('create', [Task::class, $project])) {
                    $validator->errors()->add('assignee_id', 'Assignee must be a project contributor.');
                }
            },
        ];
    }
}
