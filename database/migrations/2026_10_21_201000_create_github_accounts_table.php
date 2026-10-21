<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // GitHub's stable numeric ID is the identity; logins can change.
            $table->unsignedBigInteger('github_user_id')->unique();
            $table->string('github_login', 100);
            $table->string('avatar_url', 2048)->nullable();
            $table->timestamp('connected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_accounts');
    }
};
