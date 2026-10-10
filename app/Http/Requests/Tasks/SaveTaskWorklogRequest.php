<?php

namespace App\Http\Requests\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskWorklog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveTaskWorklogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $task = $this->route('task');
        $worklog = $this->route('worklog');

        if (! $project instanceof Project || ! $task instanceof Task) {
            return false;
        }

        abort_unless($task->project_id === $project->id, 404);
        if ($worklog instanceof TaskWorklog) {
            abort_unless($worklog->task_id === $task->id, 404);
        }

        return $this->user() !== null
            && ($worklog instanceof TaskWorklog
                ? $this->user()->can('update', $worklog)
                : $this->user()->can('create', [TaskWorklog::class, $task]));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $description = trim($this->input('description'));
            $this->merge(['description' => $description === '' ? null : $description]);
        }
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date_format:Y-m-d'],
            // The browser submits its local timezone so that 'today' agrees with the date picker.
            'timezone' => ['sometimes', 'required', 'timezone'],
            'hours' => ['required', 'integer', 'between:0,24'],
            'minutes' => ['required', 'integer', 'between:0,59'],
            'description' => ['nullable', 'string', 'max:4000'],
            'is_creative' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $validator->errors()->has('work_date') && ! $validator->errors()->has('timezone')) {
                $timezone = (string) $this->input('timezone', 'UTC');
                if ((string) $this->input('work_date') > now($timezone)->toDateString()) {
                    $validator->errors()->add('work_date', 'The work date cannot be in the future.');
                }
            }

            if ($validator->errors()->has('hours') || $validator->errors()->has('minutes')) {
                return;
            }

            $minutes = (int) $this->input('hours') * 60 + (int) $this->input('minutes');
            if ($minutes < 1 || $minutes > 1440) {
                $validator->errors()->add('hours', 'Log between 1 minute and 24 hours per entry.');
            }
        });
    }
}
