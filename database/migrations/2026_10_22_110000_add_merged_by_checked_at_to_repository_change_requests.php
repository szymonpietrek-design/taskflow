<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repository_change_requests', function (Blueprint $table): void {
            // Null means never checked, even if GitHub reports no merging account.
            $table->timestamp('merged_by_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('repository_change_requests', function (Blueprint $table): void {
            $table->dropColumn('merged_by_checked_at');
        });
    }
};
