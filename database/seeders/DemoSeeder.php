<?php

namespace Database\Seeders;

use App\Actions\Tasks\EnsureDefaultTaskStatuses;
use App\Enums\ProjectRole;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Enums\ReleaseStatus;
use App\Enums\TaskActivityType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatusCategory;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Release;
use App\Models\ReleaseChangelog;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskMessage;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoScenario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Optional local demo fixtures. Never called from DatabaseSeeder.
 *
 * Reruns create missing records only and keep existing edits and user data.
 * All timeline dates are anchored to the original demo workspace creation.
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoSeeder can run only in local or testing environments.');
        }

        $password = config('demo.password');
        if (! is_string($password) || mb_strlen($password) < 12) {
            throw new RuntimeException('Set TASKFLOW_DEMO_PASSWORD (at least 12 characters) in your local .env before running DemoSeeder.');
        }

        DB::transaction(function () use ($password): void {
            $this->populate($password);
        });

        $this->command?->info('Demo data ready: TaskFlow Demo workspace, Northstar App and Studio Website.');
        $this->command?->line('Demo logins: demo.owner@taskflow.test, demo.developer@taskflow.test, demo.reviewer@taskflow.test');
        $this->command?->line('The password comes from TASKFLOW_DEMO_PASSWORD. Existing accounts and passwords were not changed.');
    }

    private function populate(string $password): void
    {
        $initial = CarbonImmutable::now()->startOfDay()->subDays(65)->addHours(9);
        $people = [
            'owner' => ['Alex Morgan', 'demo.owner@taskflow.test'],
            'developer' => ['Maya Chen', 'demo.developer@taskflow.test'],
            'reviewer' => ['Ola Nowak', 'demo.reviewer@taskflow.test'],
        ];
        $users = [];

        foreach ($people as $key => [$name, $email]) {
            /** @var User $user */
            $user = $this->once(User::class, ['email' => $email], [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => $initial->subDays(2),
                'created_at' => $initial->subDays(2),
                'updated_at' => $initial->subDays(2),
            ]);
            $users[$key] = $user;
        }

        /** @var Workspace $workspace */
        $workspace = $this->once(Workspace::class, ['slug' => 'taskflow-demo'], [
            'name' => 'TaskFlow Demo',
            'created_by' => $users['owner']->id,
            'created_at' => $initial,
            'updated_at' => $initial,
        ]);

        if ($workspace->created_by !== $users['owner']->id) {
            throw new RuntimeException('The taskflow-demo workspace slug belongs to unrelated data; no records were changed.');
        }

        // Read the original timestamp: running the seeder next week must not recreate messages.
        $base = $workspace->created_at->toImmutable();
        foreach (['owner' => WorkspaceRole::OWNER, 'developer' => WorkspaceRole::ADMIN, 'reviewer' => WorkspaceRole::MEMBER] as $key => $role) {
            $this->once(WorkspaceMember::class, ['workspace_id' => $workspace->id, 'user_id' => $users[$key]->id], [
                'role' => $role,
                'joined_at' => $base->addDay()->addMinutes(match ($key) {'owner' => 0, 'developer' => 30, default => 60}),
                'created_at' => $base->addDay(),
                'updated_at' => $base->addDay(),
            ]);
        }

        $application = $this->project($workspace, $users['owner'], 'northstar-app', 'Northstar App', 'NSTAR', ProjectType::APPLICATION, 'A project-management application with releases, notifications, AI summaries, and task discussions.', $base->addDays(2));
        $website = $this->project($workspace, $users['owner'], 'studio-website', 'Studio Website', 'STUDIO', ProjectType::STANDARD, 'An editorial website with design, content, and performance work.', $base->addDays(4));

        foreach ([$application, $website] as $project) {
            foreach (['owner' => ProjectRole::MANAGER, 'developer' => ProjectRole::MEMBER, 'reviewer' => ProjectRole::MEMBER] as $key => $role) {
                $this->once(ProjectMember::class, ['project_id' => $project->id, 'user_id' => $users[$key]->id], [
                    'role' => $role,
                    'created_at' => $project->created_at->addMinutes(match ($key) {'owner' => 0, 'developer' => 20, default => 45}),
                    'updated_at' => $project->created_at->addMinutes(match ($key) {'owner' => 0, 'developer' => 20, default => 45}),
                ]);
            }
        }

        $statuses = [];
        foreach ([$application, $website] as $project) {
            $hadStatuses = $project->taskStatuses()->exists();
            $all = app(EnsureDefaultTaskStatuses::class)->handle($project);
            if (! $hadStatuses) {
                foreach ($all as $status) {
                    $createdAt = $project->created_at->copy()->addHours(2);
                    $status->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
                }
            }
            $statuses[$project->id] = [
                'todo' => $all->first(fn (TaskStatus $item): bool => $item->category === TaskStatusCategory::TODO),
                'in-progress' => $all->first(fn (TaskStatus $item): bool => $item->category === TaskStatusCategory::IN_PROGRESS),
                'done' => $all->first(fn (TaskStatus $item): bool => $item->category === TaskStatusCategory::DONE),
            ];
            if (in_array(null, $statuses[$project->id], true)) {
                throw new RuntimeException('Demo project needs Todo, In Progress, and Done task statuses.');
            }
        }

        $releases = [];
        foreach ([
            ['1.0.0', 1, 0, 0, 'First stable release', ReleaseStatus::RELEASED, 4, 30, null],
            ['1.1.0', 1, 1, 0, 'Team collaboration', ReleaseStatus::PLANNED, 32, null, 80],
            ['1.2.0', 1, 2, 0, 'Security and preferences', ReleaseStatus::DRAFT, 55, null, null],
        ] as [$version, $major, $minor, $patch, $name, $status, $createdDay, $releasedDay, $plannedDay]) {
            /** @var Release $release */
            $release = $this->once(Release::class, ['project_id' => $application->id, 'version' => $version], [
                'created_by' => $users['owner']->id,
                'version_major' => $major,
                'version_minor' => $minor,
                'version_patch' => $patch,
                'name' => $name,
                'description' => 'A milestone for the Northstar application.',
                'status' => $status,
                'planned_for' => $plannedDay === null ? null : $base->addDays($plannedDay),
                'released_at' => $releasedDay === null ? null : $base->addDays($releasedDay)->addHours(4),
                'created_at' => $base->addDays($createdDay),
                'updated_at' => $base->addDays($releasedDay ?? $createdDay),
            ]);
            $releases[$version] = $release;
        }

        $appTasks = $this->tasks($application, DemoScenario::applicationTasks(), $statuses[$application->id], $users, $releases, $base);
        $this->tasks($website, DemoScenario::websiteTasks(), $statuses[$website->id], $users, [], $base);

        $messages = [];
        foreach (DemoScenario::messages() as [$number, $day, $minute, $authorKey, $text]) {
            /** @var TaskMessage $message */
            $message = $this->once(TaskMessage::class, [
                'task_id' => $appTasks[$number]->id,
                'created_at' => $this->moment($base, $day, $minute)->toDateTimeString(),
            ], [
                'user_id' => $users[$authorKey]->id,
                'body' => $text,
                'updated_at' => $this->moment($base, $day, $minute),
            ]);
            $messages[$number][] = $message;
        }

        // Keep this task independent. Its provenance points back to a discussion message.
        $followup = $appTasks[18];
        if ($followup->wasRecentlyCreated && isset($messages[7][2])) {
            $followup->forceFill([
                'source_task_id' => $appTasks[7]->id,
                'source_message_id' => $messages[7][2]->id,
                'updated_at' => $base->addDays(57)->addHours(11),
            ])->saveQuietly();
        }

        $publishedBody = "## Added\n- Guided workspace onboarding and role-based invitations.\n- A project overview dashboard with task progress.\n\n## Improved\n- Faster task filtering and clearer form validation.\n\n## Fixed\n- A due-date timezone issue that could move dates by one day.";
        $publishedAt = $base->addDays(31)->addHours(11);
        $this->once(ReleaseChangelog::class, ['release_id' => $releases['1.0.0']->id], [
            'draft_body' => $publishedBody,
            'draft_locale' => 'en',
            'published_body' => $publishedBody,
            'published_locale' => 'en',
            'updated_by' => $users['owner']->id,
            'published_by' => $users['owner']->id,
            'published_at' => $publishedAt,
            'created_at' => $publishedAt,
            'updated_at' => $publishedAt,
        ]);
    }

    private function project(Workspace $workspace, User $creator, string $slug, string $name, string $key, ProjectType $type, string $description, CarbonImmutable $when): Project
    {
        /** @var Project $project */
        $project = $this->once(Project::class, ['workspace_id' => $workspace->id, 'slug' => $slug], [
            'created_by' => $creator->id,
            'name' => $name,
            'key' => $key,
            'description' => $description,
            'type' => $type,
            'visibility' => ProjectVisibility::WORKSPACE,
            'task_sequence' => 0,
            'created_at' => $when,
            'updated_at' => $when,
        ]);
        if ($project->key !== $key || $project->type !== $type || $project->created_by !== $creator->id) {
            throw new RuntimeException('A reserved demo project slug belongs to unrelated data.');
        }

        return $project;
    }

    /**
     * @param array<int, array<string, mixed>> $specs
     * @param array<string, TaskStatus> $statuses
     * @param array<string, User> $users
     * @param array<string, Release> $releases
     * @return array<int, Task>
     */
    private function tasks(Project $project, array $specs, array $statuses, array $users, array $releases, CarbonImmutable $base): array
    {
        $tasks = [];
        foreach ($specs as $number => $row) {
            $created = $base->addDays($row['day'])->addMinutes(30);
            $completed = $row['status'] === 'done' ? $created->addDays(4) : null;
            /** @var Task $task */
            $task = $this->once(Task::class, ['project_id' => $project->id, 'number' => $number], [
                'release_id' => isset($row['release']) && $row['release'] !== null ? $releases[$row['release']]->id : null,
                'parent_id' => null,
                'title' => $row['title'],
                'description' => $row['description'],
                'type' => $row['type'],
                'priority' => $row['priority'],
                'status_id' => $statuses[$row['status']]->id,
                'assignee_id' => $row['assignee'] === null ? null : $users[$row['assignee']]->id,
                'created_by' => $users['owner']->id,
                'position' => $number * 1000,
                'due_date' => $created->addDays(10),
                'completed_at' => $completed,
                'created_at' => $created,
                'updated_at' => $completed ?? $created->addDays(2),
            ]);
            $tasks[$number] = $task;

            if ($row['status'] !== 'todo') {
                $this->activity($task, $users['developer'], TaskActivityType::STATUS_CHANGED, [
                    'from' => $this->statusSnapshot($statuses['todo']),
                    'to' => $this->statusSnapshot($statuses['in-progress']),
                ], $created->addDays(1));
            }
            if ($row['status'] === 'done') {
                $this->activity($task, $users['developer'], TaskActivityType::STATUS_CHANGED, [
                    'from' => $this->statusSnapshot($statuses['in-progress']),
                    'to' => $this->statusSnapshot($statuses['done']),
                ], $completed);
            }
            if ($task->release_id !== null) {
                $release = $releases[$row['release']];
                $this->activity($task, $users['owner'], TaskActivityType::RELEASE_CHANGED, [
                    'from' => null,
                    'to' => ['id' => $release->id, 'version' => $release->version],
                ], $created->addHours(4));
            }
            if (in_array($number, [6, 8, 10], true) && $project->type === ProjectType::APPLICATION) {
                $this->activity($task, $users['owner'], TaskActivityType::PRIORITY_CHANGED, [
                    'from' => ['name' => 'NORMAL', 'value' => TaskPriority::NORMAL->value],
                    'to' => ['name' => $row['priority']->name, 'value' => $row['priority']->value],
                ], $created->addDays(2)->addHours(1));
            }
        }

        $max = max((int) $project->tasks()->max('number'), (int) $project->task_sequence);
        if ($project->task_sequence < $max) {
            $lastDemoActivity = $base->addDays(62);
            $lastUpdate = $project->updated_at?->greaterThan($lastDemoActivity)
                ? $project->updated_at
                : $lastDemoActivity;
            $project->forceFill(['task_sequence' => $max, 'updated_at' => $lastUpdate])->saveQuietly();
        }

        return $tasks;
    }

    /** @return array{id:int,name:string,category:int} */
    private function statusSnapshot(TaskStatus $status): array
    {
        return ['id' => $status->id, 'name' => $status->name, 'category' => $status->category->value];
    }

    /** @param array<string, mixed> $data */
    private function activity(Task $task, User $actor, TaskActivityType $type, array $data, CarbonImmutable $time): void
    {
        $this->once(TaskActivity::class, [
            'task_id' => $task->id,
            'type' => $type->value,
            'created_at' => $time->toDateTimeString(),
        ], ['user_id' => $actor->id, 'data' => $data]);
    }

    private function moment(CarbonImmutable $base, int $day, int $minuteOfDay): CarbonImmutable
    {
        return $base->startOfDay()->addDays($day)->addMinutes($minuteOfDay);
    }

    /**
     * Does not update existing models. On reruns edited records keep their current content.
     *
     * @param class-string<Model> $modelClass
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $attributes
     */
    private function once(string $modelClass, array $identity, array $attributes): Model
    {
        $existing = $modelClass::query()->where($identity)->first();
        if ($existing !== null) {
            return $existing;
        }

        $model = new $modelClass;
        $model->forceFill([...$identity, ...$attributes])->save();

        return $model;
    }
}
