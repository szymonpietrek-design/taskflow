<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('github_repository_id')->constrained('github_repositories')->cascadeOnDelete();
            $table->char('from_sha', 40);
            $table->char('to_sha', 40)->nullable();
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->string('status', 16);
            $table->string('error_message', 255)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['github_repository_id', 'started_at'], 'gh_sync_runs_repo_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_sync_runs');
    }
};
