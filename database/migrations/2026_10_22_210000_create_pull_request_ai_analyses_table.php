<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pull_request_ai_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('repository_change_request_id')->nullable();
            $table->foreign('repository_change_request_id', 'pr_ai_request_fk')
                ->references('id')->on('repository_change_requests')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();

            // Provider-scoped identity survives repository disconnect/reconnect.
            $table->char('source_key', 64);
            $table->string('source_provider', 32);
            $table->char('source_instance_key', 64);
            $table->string('source_repository', 255);
            $table->unsignedBigInteger('source_pr_number');
            $table->string('source_head_sha', 64);
            $table->unsignedInteger('version');

            $table->string('language', 35)->default('en');
            $table->boolean('include_patch_excerpts')->default(false);
            // Populated by stage 4.2 after rechecking the live evidence.
            $table->char('input_digest', 64)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('ai_provider', 32)->nullable();
            $table->string('ai_model', 120)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            // Generated output, not raw prompts, PR bodies, patches or credentials.
            $table->longText('result_markdown')->nullable();
            // Safe application-owned error identifier, never the exception or provider response.
            $table->string('error_code', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'source_key', 'version'], 'pr_ai_project_source_version_uniq');
            $table->index(['project_id', 'created_at'], 'pr_ai_project_created_idx');
            $table->index(['project_id', 'status'], 'pr_ai_project_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_request_ai_analyses');
    }
};
