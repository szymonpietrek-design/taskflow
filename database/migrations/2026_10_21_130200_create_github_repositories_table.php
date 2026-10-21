<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional: a failed CREATE migration may have
        // already created this table before a later ADD INDEX statement failed.
        // Repair that partial migration without dropping existing records.
        if (Schema::hasTable('github_repositories')) {
            if (! Schema::hasIndex('github_repositories', 'gh_repos_install_repo_idx')) {
                Schema::table('github_repositories', function (Blueprint $table): void {
                    $table->index(
                        ['github_installation_id', 'github_repository_id'],
                        'gh_repos_install_repo_idx',
                    );
                });
            }

            return;
        }

        Schema::create('github_repositories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('github_installation_id')->constrained('github_installations')->restrictOnDelete();
            $table->unsignedBigInteger('github_repository_id');
            $table->string('full_name');
            $table->string('default_branch');
            $table->string('start_sha', 40);
            $table->string('last_synced_sha', 40);
            $table->timestamp('connected_at');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index(
                ['github_installation_id', 'github_repository_id'],
                'gh_repos_install_repo_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_repositories');
    }
};
