<?php

namespace App\Http\Requests\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace
            && ($this->user()?->can('manageMembers', $workspace) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspace = $this->route('workspace');
        $isOwner = $workspace instanceof Workspace
            && $this->user()?->workspaceMemberships()
                ->where('workspace_id', $workspace->id)
                ->first()?->role === WorkspaceRole::OWNER;

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in($isOwner ? ['member', 'admin'] : ['member'])],
        ];
    }
}
