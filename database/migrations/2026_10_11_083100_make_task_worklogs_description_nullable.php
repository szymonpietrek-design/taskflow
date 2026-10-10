<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_worklogs', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Preserve worklog entries when reverting to the original NOT NULL definition.
        DB::table('task_worklogs')->whereNull('description')->update(['description' => '']);

        Schema::table('task_worklogs', function (Blueprint $table): void {
            $table->text('description')->nullable(false)->change();
        });
    }
};
