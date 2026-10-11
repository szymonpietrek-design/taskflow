<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\CreateReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Models\Project;
use App\Models\Report;
use App\Support\Reports\ReportSourceOverview;
use App\Support\Reports\ReportAccess;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        Gate::forUser($request->user())->authorize('view', $project);
        $this->syncWorkspace($request, $project);

        return Inertia::render('reports/Index', [
            'project' => $this->projectData($project),
            'can_create' => app(ReportAccess::class)->canCreate($request->user(), $project),
            'can_select_people' => app(ReportAccess::class)->canManageTeam($request->user(), $project),
            'contributors' => app(ReportAccess::class)->canManageTeam($request->user(), $project) ? $this->contributors($project) : [],
            'reports' => $project->reports()->with('creator:id,name')
                ->when(! app(ReportAccess::class)->canManageTeam($request->user(), $project),
                    fn ($query) => $query->where('created_by', $request->user()->id)->whereJsonContains('contributor_ids', $request->user()->id))
                ->latest('id')
                ->paginate(15)->through(fn (Report $report): array => $this->reportData($report)),
        ]);
    }

    public function store(StoreReportRequest $request, Project $project, CreateReport $action): RedirectResponse
    {
        $data = $request->validated();
        if (! app(ReportAccess::class)->canManageTeam($request->user(), $project)) {
            $data['contributor_ids'] = [$request->user()->id];
        }
        $report = $action->handle($request->user(), $project, $data);
        $this->syncWorkspace($request, $project);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report draft created.')]);

        return to_route('reports.show', [$project, $report]);
    }

    public function show(Request $request, Project $project, Report $report, ReportSourceOverview $overview): Response
    {
        Gate::forUser($request->user())->authorize('view', $project);
        abort_unless($report->project_id === $project->id, 404);
        abort_unless(app(ReportAccess::class)->canView($request->user(), $report), 403);
        $this->syncWorkspace($request, $project);
        $report->load('creator:id,name');

        return Inertia::render('reports/Show', [
            'project' => $this->projectData($project),
            'report' => $this->reportData($report),
            'source_counts' => $overview->forReport($report),
            'can_delete' => app(ReportAccess::class)->canEdit($request->user(), $report),
            'can_generate' => app(ReportAccess::class)->canEdit($request->user(), $report),
            'ai_configured' => config('services.ai_chat.provider') === 'deepseek'
                && filled(config('services.ai_chat.api_key')),
            'default_ai_language' => preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{2,8})*\z/i', str_replace('_', '-', app()->getLocale()))
                ? str_replace('_', '-', app()->getLocale()) : 'en',
            'report_html' => $report->body !== null ? Str::markdown($report->body, [
                'html_input' => 'strip', 'allow_unsafe_links' => false,
            ]) : null,
        ]);
    }

    public function destroy(Request $request, Project $project, Report $report): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('view', $project);
        abort_unless($report->project_id === $project->id, 404);
        abort_unless(app(ReportAccess::class)->canEdit($request->user(), $report), 403);
        abort_if($project->archived_at !== null, 403);
        $report->delete();
        $this->syncWorkspace($request, $project);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report deleted.')]);

        return to_route('reports.index', $project);
    }

    /** @return list<array{id:int,name:string}> */
    private function contributors(Project $project): array
    {
        return User::query()
            ->where(fn ($query) => $query
                ->whereHas('workspaceMemberships', fn ($q) => $q->where('workspace_id', $project->workspace_id))
                ->orWhereHas('projectMemberships', fn ($q) => $q->where('project_id', $project->id)))
            ->orderBy('name')->limit(100)->get(['id', 'name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])->all();
    }

    private function syncWorkspace(Request $request, Project $project): void
    {
        $request->session()->put('current_workspace_id', $project->workspace_id);
    }

    /** @return array{id:int,name:string,key:string} */
    private function projectData(Project $project): array
    {
        return ['id' => $project->id, 'name' => $project->name, 'key' => $project->key];
    }

    /** @return array<string, mixed> */
    private function reportData(Report $report): array
    {
        return [
            'id' => $report->id,
            'title' => $report->title,
            'format' => $report->format->value,
            'contributor_ids' => $report->contributor_ids,
            'contributor_names' => $report->contributor_ids === null ? null : User::query()
                ->whereIn('id', $report->contributor_ids)->orderBy('name')->pluck('name')->all(),
            'period_start' => $report->period_start->toDateString(),
            'period_end' => $report->period_end->toDateString(),
            'sources' => $report->sources,
            'is_generated' => $report->generated_at !== null,
            'generated_locale' => $report->generated_locale,
            'generated_at' => $report->generated_at?->toIso8601String(),
            'body' => $report->body,
            'created_by' => $report->creator?->name,
            'created_at' => $report->created_at?->toIso8601String(),
        ];
    }
}
