<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('installation_id')->unique();
            $table->unsignedBigInteger('github_account_id');
            $table->string('account_login');
            $table->string('account_type', 40);
            $table->timestamp('connected_at');
            $table->timestamps();
            $table->index(['workspace_id', 'account_login']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_installations');
    }
};
