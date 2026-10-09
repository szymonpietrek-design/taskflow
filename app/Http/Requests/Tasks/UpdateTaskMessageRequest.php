<?php

namespace App\Http\Requests\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskMessageRequest extends FormRequest
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

        return $this->user() !== null && $this->user()->can('update', $message);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
