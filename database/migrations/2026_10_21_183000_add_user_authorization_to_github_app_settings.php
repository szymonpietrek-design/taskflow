<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('github_app_settings', function (Blueprint $table): void {
            $table->string('client_id', 150)->nullable();
            $table->text('client_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('github_app_settings', function (Blueprint $table): void {
            $table->dropColumn(['client_id', 'client_secret']);
        });
    }
};
