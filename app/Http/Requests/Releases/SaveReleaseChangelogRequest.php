<?php

namespace App\Http\Requests\Releases;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

final class SaveReleaseChangelogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('update', $project) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['draft_body' => ['present', 'nullable', 'string', 'max:50000']];
    }
}
