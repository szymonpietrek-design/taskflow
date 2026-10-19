<?php

namespace App\Http\Requests\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'body' => ['required', 'string', 'max:10000'],
            'attachment_ids' => ['sometimes', 'array', 'max:20'],
            'attachment_ids.*' => [
                'required', 'integer', 'distinct',
                Rule::exists('task_attachments', 'id')->where('task_id', $task?->id),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $message = $this->route('message');
            $incoming = $this->input('attachment_ids', []);
            if (! $message instanceof TaskMessage || ! is_array($incoming) || $validator->errors()->count() > 0) {
                return;
            }
            $existing = $message->attachments()->pluck('task_attachments.id')->all();
            if (count(array_unique(array_merge($existing, $incoming))) > 20) {
                $validator->errors()->add('attachment_ids', __('validation.max.array', ['attribute' => __('validation.attributes.files'), 'max' => 20]));
            }
        });
    }
}
