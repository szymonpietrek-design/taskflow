<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only analysis history. Only stage 4.2 will transition a pending row. */
#[Fillable([
    'project_id', 'repository_change_request_id', 'requested_by',
    'source_key', 'source_provider', 'source_instance_key', 'source_repository',
    'source_pr_number', 'source_head_sha', 'version', 'language',
    'include_patch_excerpts', 'input_digest', 'status', 'ai_provider',
    'ai_model', 'prompt_tokens', 'completion_tokens', 'result_markdown',
    'error_code', 'started_at', 'finished_at',
])]
class PullRequestAiAnalysis extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'pull_request_ai_analyses';

    /** Avoid accidentally serializing generated content and evidence fingerprints. */
    protected $hidden = ['result_markdown', 'input_digest'];

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'repository_change_request_id' => 'integer',
            'requested_by' => 'integer',
            'source_pr_number' => 'integer',
            'version' => 'integer',
            'include_patch_excerpts' => 'boolean',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(RepositoryChangeRequest::class, 'repository_change_request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
