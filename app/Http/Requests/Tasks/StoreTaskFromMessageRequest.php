<?php

namespace App\Http\Requests\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;

class StoreTaskFromMessageRequest extends StoreTaskRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $task = $this->route('task');
        $message = $this->route('message');

        if (! $project instanceof Project || ! $task instanceof Task || ! $message instanceof TaskMessage) {
            return false;
        }

        abort_unless($task->project_id === $project->id && $message->task_id === $task->id, 404);

        return parent::authorize()
            && $this->user()?->can('view', $task) === true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['parent_id'] = ['prohibited'];

        return $rules;
    }
}
