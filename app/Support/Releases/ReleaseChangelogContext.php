<?php

namespace App\Support\Releases;

use App\Enums\TaskStatusCategory;
use App\Models\Release;
use App\Models\Task;
use App\Support\Vcs\VcsSummaryEvidence;
use InvalidArgumentException;
use JsonException;

final class ReleaseChangelogContext
{
    public function __construct(private readonly VcsSummaryEvidence $vcs) {}

    /** @throws JsonException */
    public function build(Release $release, bool $includeVcs = false): string
    {
        // The model must never receive unfinished or canceled tasks as source
        // material. A category of Done AND a completion timestamp are required.
        // Apply the limit after filtering: a release can contain many planned tasks.
        $tasks = $release->tasks()
            ->whereNotNull('completed_at')
            ->whereHas('status', fn ($query) => $query->where('category', TaskStatusCategory::DONE->value))
            ->with('status')
            ->orderBy('number')
            ->limit(41)
            ->get();

        if ($tasks->isEmpty()) {
            throw new InvalidArgumentException(__('Complete at least one task assigned to this release before generating a changelog.'));
        }
        if ($tasks->count() > 40) {
            throw new InvalidArgumentException(__('This release has too many completed tasks for one AI request (maximum 40).'));
        }

        $items = $tasks->map(function (Task $task) use ($release): array {
            $messages = $task->messages()->orderByDesc('id')->limit(11)->get();
            $activities = $task->activities()->orderByDesc('id')->limit(16)->get();

            return [
                'reference' => $release->project->key.'-'.$task->number,
                'title' => $task->title,
                'description' => $this->shorten($task->description, 2200),
                'type' => $task->type->name,
                'priority' => $task->priority->name,
                'status' => $task->status?->name,
                'completed' => $task->completed_at !== null,
                'messages' => $messages->take(10)->reverse()->values()->map(fn ($message): array => [
                    'text' => $this->shorten($message->body, 1000),
                    'date' => $message->created_at?->toDateString(),
                ])->all(),
                'messages_truncated' => $messages->count() > 10,
                'activities' => $activities->take(15)->reverse()->values()->map(fn ($activity): array => [
                    'type' => $activity->type->name,
                    'data' => $activity->data,
                    'date' => $activity->created_at?->toDateString(),
                ])->all(),
                'activities_truncated' => $activities->count() > 15,
            ];
        })->all();

        $data = [
            'project' => $release->project->name,
            'project_key' => $release->project->key,
            'release' => $release->version,
            'release_status' => $release->status->name,
            'release_name' => $release->name,
            'only_completed_tasks' => true,
            'tasks' => $items,
        ];
        if ($includeVcs) {
            $data['vcs'] = $this->vcs->forRelease($release->project_id, array_column($items, 'reference'));
        }

        $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Refuse oversized payloads rather than submitting a partial, misleading release summary.
        if (strlen($payload) > 90000) {
            throw new InvalidArgumentException(__('Release source data is too large for one AI request.'));
        }

        return $payload;
    }

    private function shorten(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit).' [truncated]' : $value;
    }
}
