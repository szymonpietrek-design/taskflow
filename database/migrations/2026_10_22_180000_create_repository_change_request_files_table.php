<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_change_request_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_change_request_id');
            // Git paths may exceed the database index byte limit; compare using a full SHA-256 path hash.
            $table->text('path');
            $table->char('path_hash', 64);
            $table->text('previous_path')->nullable();
            $table->string('status', 24);
            $table->unsignedInteger('additions')->default(0);
            $table->unsignedInteger('deletions')->default(0);
            $table->unsignedInteger('changes')->default(0);
            $table->string('blob_sha', 64)->nullable();
            // Patches are strictly bounded. Never send them to an AI provider automatically.
            $table->text('patch')->nullable();
            $table->boolean('patch_truncated')->default(false);
            $table->timestamps();

            $table->foreign('repository_change_request_id', 'repo_pr_files_request_fk')
                ->references('id')->on('repository_change_requests')->cascadeOnDelete();
            $table->unique(['repository_change_request_id', 'path_hash'], 'repo_pr_files_path_unique');
        });

        Schema::table('repository_change_requests', function (Blueprint $table): void {
            // A file snapshot is current only when files_head_sha === head_sha.
            $table->string('files_head_sha', 64)->nullable();
            $table->timestamp('files_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_change_request_files');
        Schema::table('repository_change_requests', function (Blueprint $table): void {
            $table->dropColumn(['files_head_sha', 'files_synced_at']);
        });
    }
};
