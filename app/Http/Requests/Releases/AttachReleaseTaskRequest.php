<?php

namespace App\Http\Requests\Releases;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachReleaseTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('view', $project) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'task_id' => [
                'required', 'integer', Rule::exists('tasks', 'id')->where('project_id', $project->id),
            ],
        ];
    }
}
