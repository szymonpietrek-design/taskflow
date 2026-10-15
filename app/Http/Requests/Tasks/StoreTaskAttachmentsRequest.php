<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreTaskAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && $task->project_id === $this->route('project')?->id
            && Gate::forUser($this->user())->allows('update', $task);
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf,txt,json,zip'],
        ];
    }
}
