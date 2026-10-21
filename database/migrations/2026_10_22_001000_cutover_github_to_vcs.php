<?php

use App\Integrations\Vcs\BackfillGitHubLegacyData;
use App\Integrations\Vcs\Providers\GitHubInstance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DDL may commit outside a MySQL transaction; make a retry safe.
        if (! Schema::hasColumn('vcs_instances', 'cutover_at')) {
            Schema::table('vcs_instances', function (Blueprint $table): void {
                $table->timestamp('cutover_at')->nullable();
            });
        }

        // No application traffic should write commits while this migration executes.
        DB::transaction(function (): void {
            $instance = GitHubInstance::get();
            app(BackfillGitHubLegacyData::class)->run();
            // Never allow the old backfill command to overwrite the live VCS cursor.
            DB::table('vcs_instances')->where('id', $instance->id)->update([
                'cutover_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Existing VCS records and history are intentionally preserved on rollback.
        if (Schema::hasColumn('vcs_instances', 'cutover_at')) {
            Schema::table('vcs_instances', function (Blueprint $table): void {
                $table->dropColumn('cutover_at');
            });
        }
    }
};
