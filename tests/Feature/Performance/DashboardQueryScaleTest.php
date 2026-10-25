<?php

namespace Tests\Feature\Performance;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardQueryScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_select_query_count_does_not_grow_with_project_count(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        WorkspaceMember::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::OWNER,
        ]);
        Project::factory()->create(['workspace_id' => $workspace->id]);

        $selects = 0;
        DB::listen(function (QueryExecuted $query) use (&$selects): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select ')) {
                $selects++;
            }
        });

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        $oneProjectQueries = $selects;

        foreach (range(2, 20) as $number) {
            Project::factory()->create(['workspace_id' => $workspace->id]);
        }

        $selects = 0;
        $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        $twentyProjectQueries = $selects;

        // The previous per-project ProjectPolicy::view check added at least
        // one SELECT per project (often two). Allow a small fixed variance for
        // other eager-loaded dashboard data across database drivers.
        $this->assertLessThanOrEqual(
            $oneProjectQueries + 3,
            $twentyProjectQueries,
            "Dashboard queries grew from {$oneProjectQueries} to {$twentyProjectQueries} for 20 projects.",
        );
    }
}
