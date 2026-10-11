<?php

namespace App\Http\Requests\Worklogs;

use App\Http\Requests\Tasks\SaveTaskWorklogRequest;
use App\Models\Task;
use App\Models\TaskWorklog;

/** Reuse task worklog date/duration validation and all its safety checks. */
class SaveGlobalWorklogRequest extends SaveTaskWorklogRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $worklog = $this->route('worklog');
        if ($worklog instanceof TaskWorklog) {
            return $this->user()->can('update', $worklog);
        }

        $taskId = $this->input('task_id');
        if (! is_int($taskId) && ! (is_string($taskId) && ctype_digit($taskId))) {
            return false;
        }

        $task = Task::query()->find($taskId);

        return $task !== null && $this->user()->can('create', [TaskWorklog::class, $task]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'task_id' => $this->route('worklog') instanceof TaskWorklog
                ? ['prohibited']
                : ['required', 'integer', 'exists:tasks,id'],
        ];
    }
}
