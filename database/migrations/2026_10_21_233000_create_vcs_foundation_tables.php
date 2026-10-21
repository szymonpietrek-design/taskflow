<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provider-scoped instance identity: e.g. github.com, gitlab.com or a
        // particular self-hosted GitLab/Gitea. The SHA-256 key is derived from
        // the canonical base URL (see the provider adapter in the next stage).
        Schema::create('vcs_instances', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('base_url', 2048);
            $table->char('instance_key', 64);
            $table->timestamps();
            $table->unique(['provider', 'instance_key'], 'vcs_instance_provider_key_uniq');
        });

        Schema::create('vcs_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vcs_instance_id')->constrained('vcs_instances')->restrictOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('external_connection_id', 190);
            $table->string('external_account_id', 190)->nullable();
            $table->string('account_login', 190);
            $table->string('account_type', 40)->nullable();
            $table->timestamp('connected_at');
            // Mapping kept until the GitHub adapter is cut over and verified.
            $table->unsignedBigInteger('legacy_github_installation_id')->nullable()->unique('vcs_conn_legacy_gh_uniq');
            $table->timestamps();
            $table->unique(['vcs_instance_id', 'external_connection_id'], 'vcs_conn_instance_external_uniq');
            $table->index(['workspace_id', 'vcs_instance_id'], 'vcs_conn_workspace_instance_idx');
        });

        Schema::create('repository_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('vcs_connection_id')->constrained('vcs_connections')->restrictOnDelete();
            $table->string('external_repository_id', 190);
            $table->string('full_name');
            $table->string('default_branch');
            $table->string('start_sha', 64);
            $table->string('last_synced_sha', 64);
            $table->timestamp('connected_at');
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('legacy_github_repository_id')->nullable()->unique('repo_conn_legacy_gh_uniq');
            $table->timestamps();
            $table->index(['vcs_connection_id', 'external_repository_id'], 'repo_conn_provider_repo_idx');
        });

        Schema::create('repository_commits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_connection_id')->constrained('repository_connections')->cascadeOnDelete();
            $table->string('sha', 64);
            $table->text('message');
            $table->string('author_name', 190)->nullable();
            $table->string('author_login', 190)->nullable();
            $table->string('author_external_user_id', 190)->nullable();
            $table->timestamp('author_identity_checked_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->unsignedBigInteger('legacy_github_commit_id')->nullable()->unique('repo_commits_legacy_gh_uniq');
            $table->timestamps();
            $table->unique(['repository_connection_id', 'sha'], 'repo_commits_repo_sha_uniq');
            $table->index(['repository_connection_id', 'committed_at'], 'repo_commits_repo_date_idx');
            $table->index('author_external_user_id', 'repo_commits_author_ext_idx');
        });

        Schema::create('repository_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_connection_id')->constrained('repository_connections')->cascadeOnDelete();
            $table->string('from_sha', 64);
            $table->string('to_sha', 64)->nullable();
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->string('status', 16);
            $table->string('error_message', 255)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('legacy_github_sync_run_id')->nullable()->unique('repo_runs_legacy_gh_uniq');
            $table->timestamps();
            $table->index(['repository_connection_id', 'started_at'], 'repo_runs_repo_date_idx');
        });

        Schema::create('user_vcs_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vcs_instance_id')->constrained('vcs_instances')->restrictOnDelete();
            $table->string('external_user_id', 190);
            $table->string('login', 190);
            $table->string('avatar_url', 2048)->nullable();
            $table->timestamp('connected_at');
            $table->unsignedBigInteger('legacy_github_account_id')->nullable()->unique('user_vcs_legacy_gh_uniq');
            $table->timestamps();
            // Each person may link one verified account per provider instance.
            $table->unique(['user_id', 'vcs_instance_id'], 'user_vcs_user_instance_uniq');
            $table->unique(['vcs_instance_id', 'external_user_id'], 'user_vcs_instance_external_uniq');
        });
    }

    public function down(): void
    {
        // Old github_* tables deliberately remain untouched on rollback.
        Schema::dropIfExists('user_vcs_accounts');
        Schema::dropIfExists('repository_sync_runs');
        Schema::dropIfExists('repository_commits');
        Schema::dropIfExists('repository_connections');
        Schema::dropIfExists('vcs_connections');
        Schema::dropIfExists('vcs_instances');
    }
};
