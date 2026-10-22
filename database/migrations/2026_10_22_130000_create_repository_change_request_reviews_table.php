<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A failed MySQL CREATE TABLE may leave this column behind because DDL is not transactional.
        if (! Schema::hasColumn('repository_change_requests', 'reviews_checked_at')) {
            Schema::table('repository_change_requests', function (Blueprint $table): void {
                // Checked separately from PR metadata: a review can be dismissed after the PR is saved.
                $table->timestamp('reviews_checked_at')->nullable();
            });
        }

        Schema::create('repository_change_request_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_change_request_id');
            // MySQL limits constraint names to 64 characters: use a short explicit name.
            $table->foreign('repository_change_request_id', 'repo_review_request_fk')
                ->references('id')->on('repository_change_requests')->cascadeOnDelete();
            // A provider's stable review ID is not the PR number or the reviewer ID.
            $table->string('external_review_id', 190);
            $table->string('reviewer_external_user_id', 190)->nullable();
            $table->string('reviewer_login', 190)->nullable();
            $table->string('state', 32);
            $table->longText('body')->nullable();
            $table->string('web_url', 2048)->nullable();
            $table->string('commit_sha', 64)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['repository_change_request_id', 'external_review_id'], 'repo_review_cr_external_uniq');
            $table->index(['repository_change_request_id', 'submitted_at'], 'repo_review_cr_submitted_idx');
            $table->index('reviewer_external_user_id', 'repo_review_reviewer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_change_request_reviews');
        if (Schema::hasColumn('repository_change_requests', 'reviews_checked_at')) {
            Schema::table('repository_change_requests', function (Blueprint $table): void {
                $table->dropColumn('reviews_checked_at');
            });
        }
    }
};
