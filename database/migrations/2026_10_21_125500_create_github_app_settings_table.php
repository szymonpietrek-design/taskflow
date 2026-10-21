<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_app_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('app_id');
            // Laravel's encrypted cast ensures the PEM is never persisted in plaintext.
            $table->text('private_key');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_app_settings');
    }
};
