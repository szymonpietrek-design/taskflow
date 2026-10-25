<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repository_commits', function (Blueprint $table): void {
            // Null means not inspected. A zero count means a verified empty snapshot.
            $table->timestamp('files_synced_at')->nullable();
            $table->unsignedInteger('files_count')->nullable();
        });

        Schema::create('repository_commit_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_commit_id')->constrained('repository_commits')->cascadeOnDelete();
            $table->string('path', 1024);
            $table->char('path_hash', 64);
            $table->string('previous_path', 1024)->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('additions');
            $table->unsignedInteger('deletions');
            $table->unsignedInteger('changes');
            $table->timestamps();
            $table->unique(['repository_commit_id', 'path_hash'], 'repo_commit_file_path_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_commit_files');
        Schema::table('repository_commits', function (Blueprint $table): void {
            $table->dropColumn(['files_synced_at', 'files_count']);
        });
    }
};
