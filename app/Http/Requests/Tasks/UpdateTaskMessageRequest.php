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
            'sync_attachments' => ['sometimes', 'boolean'],
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
            // Sync submits the complete final set; legacy add-only requests
            // must still respect the combined existing + incoming limit.
            $selected = $this->boolean('sync_attachments')
                ? $incoming
                : array_merge($message->attachments()->pluck('task_attachments.id')->all(), $incoming);
            if (count(array_unique($selected)) > 20) {
                $validator->errors()->add('attachment_ids', __('validation.max.array', ['attribute' => __('validation.attributes.files'), 'max' => 20]));
            }

            // Inline attachment references may only point to files linked to the message.
            // Otherwise the safe Markdown renderer shows "Attachment unavailable".
            $body = $this->input('body');
            if (is_string($body) && preg_match_all('/attachment:([1-9][0-9]*)/i', $body, $matches)) {
                $selectedIds = array_map('intval', $selected);
                foreach (array_unique($matches[1]) as $referencedId) {
                    if (! in_array((int) $referencedId, $selectedIds, true)) {
                        $validator->errors()->add('attachment_ids', __('A file referenced in the message must remain attached.'));
                        break;
                    }
                }
            }
        });
    }
}
