<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
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
        $workspaceId = $this->session()->get('current_workspace_id');

        if ($user === null || ! is_numeric($workspaceId)) {
            return false;
        }

        $workspace = $user->workspaces()->whereKey((int) $workspaceId)->first();

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
        $workspaceId = (int) $this->session()->get('current_workspace_id');
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
            'type' => ['required', Rule::in($types)],
            'visibility' => ['required', Rule::in($visibilities)],
        ];
    }
}
