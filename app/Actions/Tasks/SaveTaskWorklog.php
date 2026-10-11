<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\TaskWorklog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Persist a worklog from either the task view or the user's global diary. */
class SaveTaskWorklog
{
    /** @param array<string, mixed> $data */
    public function handle(User $user, Task $task, array $data, ?TaskWorklog $worklog = null): TaskWorklog
    {
        return DB::transaction(function () use ($user, $task, $data, $worklog): TaskWorklog {
            // Serialize concurrent writes for the same user, regardless of project or task.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $duration = (int) $data['hours'] * 60 + (int) $data['minutes'];
            $onDate = TaskWorklog::query()
                ->where('user_id', $user->id)
                ->whereDate('work_date', $data['work_date']);

            if ($worklog !== null) {
                $onDate->where('id', '!=', $worklog->id);
            }

            $alreadyLogged = (int) $onDate->sum('duration_minutes');
            if ($alreadyLogged + $duration > TaskWorklog::DAILY_LIMIT_MINUTES) {
                $available = max(0, TaskWorklog::DAILY_LIMIT_MINUTES - $alreadyLogged);
                $remaining = sprintf('%dh %dm', intdiv($available, 60), $available % 60);

                throw ValidationException::withMessages([
                    'hours' => 'Daily limit: 8 hours per user across all tasks and projects. Remaining on this date: '.$remaining.'.',
                ]);
            }

            $attributes = [
                'work_date' => $data['work_date'],
                'duration_minutes' => $duration,
                'description' => $data['description'] ?? null,
                'is_creative' => $data['is_creative'] ?? false,
            ];

            if ($worklog !== null) {
                $worklog->update($attributes);
                return $worklog;
            }

            return $task->worklogs()->create(['user_id' => $user->id, ...$attributes]);
        });
    }
}
