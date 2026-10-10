<?php

namespace App\Http\Controllers\Releases;

use App\Actions\Releases\CreateRelease;
use App\Enums\TaskStatusCategory;
use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Models\Task;
use App\Http\Controllers\Controller;
use App\Http\Requests\Releases\StoreReleaseRequest;
use App\Models\Project;
use App\Models\Release;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ReleaseController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorizeApplication($request, $project);
        $request->session()->put('current_workspace_id', $project->workspace_id);

        $canCreate = $request->user()->can('update', $project) && $project->archived_at === null;

        $releases = $project->releases()
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->paginate(15)
            ->through(fn (Release $release): array => $this->releaseData($release));

        return Inertia::render('releases/Index', [
            'project' => $this->projectData($project),
            'can_create' => $canCreate,
            'completed_available_count' => $canCreate
                ? $project->tasks()->whereNull('release_id')->whereNotNull('completed_at')
                    ->whereHas('status', fn (Builder $query) => $query->where('project_id', $project->id)
                        ->where('category', TaskStatusCategory::DONE->value))
                    ->count()
                : 0,
            'releases' => $releases,
        ]);
    }

    public function store(StoreReleaseRequest $request, Project $project, CreateRelease $createRelease): RedirectResponse
    {
        abort_unless($project->type === ProjectType::APPLICATION, 404);
        $data = $request->validated();

        try {
            $release = $createRelease->handle(
                $request->user(),
                $project,
                $data['version'],
                $data['name'] ?? null,
                $data['description'] ?? null,
                isset($data['planned_for']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['planned_for']) : null,
                $request->boolean('include_completed_tasks'),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['release' => $exception->getMessage()]);
        }

        $request->session()->put('current_workspace_id', $project->workspace_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Release created.')]);

        return to_route('releases.show', [$project, $release]);
    }

    public function show(Request $request, Project $project, Release $release): Response
    {
        $this->authorizeApplication($request, $project);
        abort_unless($release->project_id === $project->id, 404);
        $request->session()->put('current_workspace_id', $project->workspace_id);

        $editable = $project->archived_at === null
            && in_array($release->status, [ReleaseStatus::DRAFT, ReleaseStatus::PLANNED], true);
        $canManage = $editable && $request->user()->can('create', [Task::class, $project]);
        $search = trim(is_string($request->query('q')) ? $request->query('q') : '');
        if (mb_strlen($search) > 80) {
            $search = mb_substr($search, 0, 80);
        }

        $assigned = $release->tasks()->with('status')->orderByDesc('number')
            ->paginate(15, ['*'], 'tasks_page')->withQueryString();
        $available = collect();
        $availableCount = 0;
        $completedAvailableCount = 0;
        if ($canManage) {
            $query = $project->tasks()->with('status')->whereNull('release_id');
            if ($search !== '') {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $number = preg_match('/^'.preg_quote($project->key, '/').'-(\d+)$/i', $search, $matches)
                    ? (int) $matches[1] : null;
                $query->where(function (Builder $builder) use ($pattern, $number): void {
                    $builder->whereRaw("title LIKE ? ESCAPE '!'", [$pattern]);
                    if ($number !== null) {
                        $builder->orWhere('number', $number);
                    }
                });
            }
            $completedAvailableCount = $project->tasks()->whereNull('release_id')->whereNotNull('completed_at')
                ->whereHas('status', fn (Builder $builder) => $builder
                    ->where('project_id', $project->id)
                    ->where('category', TaskStatusCategory::DONE->value))->count();
            $availableCount = (clone $query)->count();
            $available = $query->orderByDesc('number')->limit(30)->get();
        }

        $canManageRelease = $project->archived_at === null && $request->user()->can('update', $project);
        $canEditChangelog = $canManageRelease && $release->status !== ReleaseStatus::CANCELED;
        $changelog = $release->changelog;
        $draft = $canEditChangelog ? ($changelog?->draft_body ?? '') : '';
        $published = $changelog?->published_body;


        return Inertia::render('releases/Show', [
            'project' => $this->projectData($project),
            'release' => $this->releaseData($release),
            'can_manage_release' => $canManageRelease,
            'can_edit_changelog' => $canEditChangelog,
            'default_ai_language' => preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})*\z/i', str_replace('_', '-', app()->getLocale()))
                ? str_replace('_', '-', app()->getLocale()) : 'en',
            'ai_configured' => config('services.ai_chat.provider') === 'deepseek'
                && filled(config('services.ai_chat.api_key')),
            'changelog' => [
                'draft_body' => $draft,
                'draft_locale' => $canEditChangelog ? $changelog?->draft_locale : null,
                'published_locale' => $changelog?->published_locale,
                'draft_html' => $canEditChangelog ? $this->renderMarkdown($draft) : null,
                'published_html' => $published !== null ? $this->renderMarkdown($published) : null,
                'published_at' => $changelog?->published_at?->toIso8601String(),
                'has_unpublished_changes' => $canEditChangelog
                    && $draft !== '' && ($draft !== ($published ?? '')
                        || $changelog?->draft_locale !== $changelog?->published_locale),
            ],
            'can_manage_tasks' => $canManage,
            'tasks' => $assigned->through(fn (Task $task): array => $this->taskData($project, $task)),
            'task_count' => $release->tasks()->count(),
            'available_tasks' => $available->map(fn (Task $task): array => $this->taskData($project, $task))->all(),
            'available_count' => $availableCount,
            'completed_available_count' => $completedAvailableCount,
            'task_search' => $search,
        ]);
    }

    private function renderMarkdown(string $body): string
    {
        return Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /** @return array{id:int,reference:string,title:string,status:string,completed:bool} */
    private function taskData(Project $project, Task $task): array
    {
        return [
            'id' => $task->id,
            'reference' => $project->key.'-'.$task->number,
            'title' => $task->title,
            'status' => $task->relationLoaded('status') ? $task->status->name : '',
            'completed' => $task->completed_at !== null,
        ];
    }

    private function authorizeApplication(Request $request, Project $project): void
    {
        Gate::forUser($request->user())->authorize('view', $project);
        abort_unless($project->type === ProjectType::APPLICATION, 404);
    }

    /** @return array{id: int, name: string, key: string} */
    private function projectData(Project $project): array
    {
        return ['id' => $project->id, 'name' => $project->name, 'key' => $project->key];
    }

    /** @return array<string, int|string|null> */
    private function releaseData(Release $release): array
    {
        return [
            'id' => $release->id,
            'version' => $release->version,
            'name' => $release->name,
            'description' => $release->description,
            'status' => strtolower($release->status->name),
            'status_label' => ucfirst(strtolower($release->status->name)),
            'planned_for' => $release->planned_for?->format('Y-m-d'),
            'released_at' => $release->released_at?->toIso8601String(),
            'created_at' => $release->created_at?->toIso8601String(),
        ];
    }
}
