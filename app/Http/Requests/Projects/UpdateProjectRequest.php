<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectVisibility;
use App\Models\Project;
use App\Models\Workspace;
use App\Support\Projects\ProjectIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');
        $project = $this->route('project');

        return $workspace instanceof Workspace
            && $project instanceof Project
            && $project->workspace_id === $workspace->id
            && $this->user()?->can('update', $project) === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'icon' => ['sometimes', 'nullable', 'string', Rule::in(ProjectIdentity::ICONS)],
            'color' => ['sometimes', 'nullable', 'string', Rule::in(ProjectIdentity::COLORS)],
            'visibility' => ['sometimes', Rule::in(array_map(
                fn (ProjectVisibility $visibility): int => $visibility->value,
                ProjectVisibility::cases(),
            ))],
        ];
    }
}
