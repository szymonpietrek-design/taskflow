<?php

namespace App\Models;

use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $created_by
 * @property string $name
 * @property string $slug
 * @property string $key
 * @property string|null $description
 * @property ProjectType $type
 * @property ProjectVisibility $visibility
 * @property int $task_sequence
 * @property string|null $icon
 * @property string|null $color
 * @property string|null $logo_path
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'workspace_id',
    'created_by',
    'name',
    'slug',
    'key',
    'description',
    'icon',
    'color',
    'type',
    'visibility',
    'task_sequence',
    'archived_at',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'visibility' => ProjectVisibility::class,
            'archived_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function taskStatuses(): HasMany
    {
        return $this->hasMany(TaskStatus::class);
    }

    /** Stored AI analyses survive repository disconnection. */
    public function pullRequestAiAnalyses(): HasMany
    {
        return $this->hasMany(PullRequestAiAnalysis::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
