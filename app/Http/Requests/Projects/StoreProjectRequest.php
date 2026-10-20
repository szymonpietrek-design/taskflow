<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Support\Projects\ProjectIdentity;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('key'))) {
            $this->merge([
                'key' => Str::upper(trim($this->input('key'))),
            ]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $workspaceId = $this->workspaceId();

        if ($user === null || ! is_numeric($workspaceId)) {
            return false;
        }

        $workspace = \App\Models\Workspace::find($workspaceId);

        return $workspace !== null
            && $user->can('create', [Project::class, $workspace]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspaceId = $this->workspaceId();
        $types = array_map(fn (ProjectType $type): int => $type->value, ProjectType::cases());
        $visibilities = array_map(
            fn (ProjectVisibility $visibility): int => $visibility->value,
            ProjectVisibility::cases(),
        );

        return [
            'name' => ['required', 'string', 'max:120'],
            'key' => [
                'required',
                'string',
                'regex:/^[A-Z][A-Z0-9]{0,11}$/',
                Rule::unique('projects', 'key')
                    ->where(fn ($query) => $query->where('workspace_id', $workspaceId)),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'icon' => ['nullable', 'string', Rule::in(ProjectIdentity::ICONS)],
            'color' => ['nullable', 'string', Rule::in(ProjectIdentity::COLORS)],
            'type' => ['required', Rule::in($types)],
            'visibility' => ['sometimes', Rule::in($visibilities)],
        ];
    }
    private function workspaceId(): ?int
    {
        $workspace = $this->route('workspace');

        if ($workspace instanceof \App\Models\Workspace) {
            return $workspace->id;
        }

        $workspaceId = $this->session()->get('current_workspace_id');

        return is_numeric($workspaceId) ? (int) $workspaceId : null;
    }

}
