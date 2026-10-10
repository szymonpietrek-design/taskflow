<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_worklogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('duration_minutes');
            $table->text('description');
            $table->boolean('is_creative')->default(false);
            $table->timestamps();

            // There is deliberately no unique(task_id, work_date): multiple logs per day are allowed.
            $table->index(['task_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_worklogs');
    }
};
