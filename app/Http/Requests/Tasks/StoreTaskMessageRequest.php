<?php

namespace App\Http\Requests\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $task = $this->route('task');

        return [
            // A message may contain only a file, only text, or both.
            'body' => ['nullable', 'string', 'max:10000', 'required_without_all:files,attachment_ids'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,txt,json,zip'],
            'attachment_ids' => ['nullable', 'array', 'max:20'],
            'attachment_ids.*' => [
                'required', 'integer', 'distinct',
                Rule::exists('task_attachments', 'id')->where('task_id', $task?->id),
            ],
        ];
    }
}
