<?php

namespace App\Support\Releases;

use App\Models\Release;
use App\Models\Task;
use InvalidArgumentException;
use JsonException;

final class ReleaseChangelogContext
{
    /** @throws JsonException */
    public function build(Release $release): string
    {
        // Fail instead of silently omitting tasks from a large release.
        $tasks = $release->tasks()->with('status')->orderBy('number')->limit(41)->get();
        if ($tasks->isEmpty()) {
            throw new InvalidArgumentException('Assign at least one task to this release before generating a changelog.');
        }
        if ($tasks->count() > 40) {
            throw new InvalidArgumentException('This release has too many tasks for one AI request (maximum 40).');
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

        $payload = json_encode([
            'project' => $release->project->name,
            'project_key' => $release->project->key,
            'release' => $release->version,
            'release_name' => $release->name,
            'release_description' => $this->shorten($release->description, 2000),
            'tasks' => $items,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Refuse oversized payloads rather than submitting a partial, misleading release summary.
        if (strlen($payload) > 90000) {
            throw new InvalidArgumentException('Release source data is too large for one AI request.');
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
