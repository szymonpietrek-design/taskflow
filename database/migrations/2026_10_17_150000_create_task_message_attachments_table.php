<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_message_attachments', function (Blueprint $table): void {
            $table->foreignId('task_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_attachment_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_message_id', 'task_attachment_id']);
            $table->index('task_attachment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_message_attachments');
    }
};
