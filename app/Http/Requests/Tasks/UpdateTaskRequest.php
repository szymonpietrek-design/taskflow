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

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $task = $this->route('task');

        if (! $project instanceof Project || ! $task instanceof Task) {
            return false;
        }

        abort_unless($task->project_id === $project->id, 404);

        return $this->user()?->can('update', $task) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            // Optional for existing API consumers; when omitted, the original type is kept.
            'type' => ['sometimes', 'required', Rule::in(array_column(TaskType::cases(), 'value'))],
            'priority' => ['required', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $assigneeId = $this->input('assignee_id');

                if ($assigneeId === null || $assigneeId === '' || $validator->errors()->has('assignee_id')) {
                    return;
                }

                /** @var Task $task */
                $task = $this->route('task');

                if ((int) $assigneeId === $task->assignee_id) {
                    return;
                }

                $assignee = User::query()->find($assigneeId);

                if ($assignee === null || Gate::forUser($assignee)->denies('create', [Task::class, $task->project])) {
                    $validator->errors()->add('assignee_id', 'Assignee must be a project contributor.');
                }
            },
        ];
    }
}
