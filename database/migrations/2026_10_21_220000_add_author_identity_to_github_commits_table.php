<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('github_commits', function (Blueprint $table): void {
            // GitHub's numeric user ID is stable; the login is only display metadata.
            $table->unsignedBigInteger('author_github_user_id')->nullable()->after('author_login');
            // Distinguishes legacy commits from commits checked with no known GitHub author.
            $table->timestamp('author_identity_checked_at')->nullable()->after('author_github_user_id');
            $table->index('author_github_user_id', 'gh_commits_author_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('github_commits', function (Blueprint $table): void {
            $table->dropIndex('gh_commits_author_id_idx');
            $table->dropColumn(['author_github_user_id', 'author_identity_checked_at']);
        });
    }
};
