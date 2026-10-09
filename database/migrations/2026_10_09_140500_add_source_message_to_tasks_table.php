<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            // Source links are provenance only. They do not define a parent/subtask relationship.
            $table->foreignId('source_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('source_message_id')->nullable()->constrained('task_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_message_id');
            $table->dropConstrainedForeignId('source_task_id');
        });
    }
};
