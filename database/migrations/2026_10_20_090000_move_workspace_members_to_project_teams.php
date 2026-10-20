<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Before this change, workspace members could read every WORKSPACE project.
        // Convert that inherited access into explicit project grants; keep existing
        // private-project grants, ownership, task references and worklog history.
        DB::transaction(function (): void {
            $memberships = DB::table('workspace_members')
                ->where('role', 3)
                ->orderBy('id')->get();
            foreach ($memberships as $member) {
                if ($member->suspended_at === null) {
                    $projects = DB::table('projects')
                        ->where('workspace_id', $member->workspace_id)
                        ->where('visibility', 1)
                        ->pluck('id');
                    foreach ($projects as $projectId) {
                        DB::table('project_members')->insertOrIgnore([
                            'project_id' => $projectId,
                            'user_id' => $member->user_id,
                            'role' => 2,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
            DB::table('workspace_members')->where('role', 3)->delete();

            // In the former model project creators (workspace admins) also had
            // explicit project-manager rows. Remove these redundant grants so
            // losing workspace administration does not leave implicit access.
            $admins = DB::table('workspace_members')->whereIn('role', [1, 2])->get();
            foreach ($admins as $admin) {
                $projects = DB::table('projects')->where('workspace_id', $admin->workspace_id)->pluck('id');
                DB::table('project_members')->where('user_id', $admin->user_id)
                    ->whereIn('project_id', $projects)->delete();
            }
        });
    }

    public function down(): void
    {
        // Irreversible intentionally: project membership can change after the
        // migration; restoring old workspace-wide access would over-grant rights.
    }
};
