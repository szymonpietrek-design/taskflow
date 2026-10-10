<?php

namespace App\Http\Requests\Releases;

use App\Models\Project;
use App\Support\Releases\ReleaseVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StoreReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('update', $project) === true;
    }

    protected function prepareForValidation(): void
    {
        $fields = [];
        foreach (['version', 'name', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $fields[$field] = trim($this->input($field));
            }
        }

        $this->merge($fields);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'version' => ['required', 'string', 'max:32', Rule::unique('releases', 'version')->where('project_id', $project->id)],
            'name' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'planned_for' => ['nullable', 'date_format:Y-m-d'],
            'include_completed_tasks' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $version = $this->input('version');

            if (! is_string($version) || $validator->errors()->has('version')) {
                return;
            }

            try {
                ReleaseVersion::parse($version);
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('version', $exception->getMessage());
            }
        }];
    }
}
