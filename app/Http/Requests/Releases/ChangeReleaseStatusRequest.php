<?php

namespace App\Http\Requests\Releases;

use App\Enums\ReleaseStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeReleaseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('update', $project) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', Rule::in([
                ReleaseStatus::PLANNED->value,
                ReleaseStatus::RELEASED->value,
                ReleaseStatus::CANCELED->value,
            ])],
            'planned_for' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
