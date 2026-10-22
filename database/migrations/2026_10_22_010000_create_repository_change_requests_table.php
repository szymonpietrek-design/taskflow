<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_connection_id')
                ->constrained('repository_connections')->cascadeOnDelete();

            // Never assume a provider's internal ID equals the public PR/MR number.
            // For GitLab these are `id` and the repository-scoped `iid` respectively.
            $table->string('external_change_request_id', 190);
            $table->unsignedBigInteger('number');
            $table->string('title', 500);
            $table->longText('body')->nullable();
            $table->string('web_url', 2048);

            // Providers normalize into open, closed or merged; draft is independent.
            // Do not use enum: adapters can normalize independently of DB migrations.
            $table->string('state', 16);
            $table->boolean('is_draft')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->string('source_branch')->nullable();
            $table->string('target_branch')->nullable();
            $table->string('source_repository')->nullable();
            $table->string('head_sha', 64)->nullable();
            $table->string('base_sha', 64)->nullable();
            $table->string('merge_commit_sha', 64)->nullable();

            // External identities must be resolved within the connection's instance.
            // UserVcsAccount mapping belongs to the later author/review stage.
            $table->string('author_external_user_id', 190)->nullable();
            $table->string('author_login', 190)->nullable();
            $table->string('merged_by_external_user_id', 190)->nullable();
            $table->string('merged_by_login', 190)->nullable();

            // Provider times and local persistence times deliberately differ.
            $table->timestamp('remote_created_at')->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['repository_connection_id', 'external_change_request_id'], 'repo_cr_repo_external_uniq');
            $table->unique(['repository_connection_id', 'number'], 'repo_cr_repo_number_uniq');
            $table->index(['repository_connection_id', 'state', 'remote_updated_at'], 'repo_cr_state_updated_idx');
            $table->index(['repository_connection_id', 'merged_at'], 'repo_cr_merged_at_idx');
            $table->index('author_external_user_id', 'repo_cr_author_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_change_requests');
    }
};
