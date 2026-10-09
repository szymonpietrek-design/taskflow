<?php

namespace App\Http\Requests\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $task = $this->route('task');

        if (! $project instanceof Project || ! $task instanceof Task) {
            return false;
        }

        abort_unless($task->project_id === $project->id, 404);

        return $this->user() !== null
            && $this->user()->can('create', [TaskMessage::class, $task]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
