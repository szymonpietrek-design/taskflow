<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_commits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('github_repository_id')->constrained('github_repositories')->cascadeOnDelete();
            $table->char('sha', 40);
            $table->text('message');
            $table->string('author_name', 190)->nullable();
            $table->string('author_login', 190)->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
            $table->unique(['github_repository_id', 'sha'], 'gh_commits_repo_sha_uniq');
            $table->index(['github_repository_id', 'committed_at'], 'gh_commits_repo_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_commits');
    }
};
