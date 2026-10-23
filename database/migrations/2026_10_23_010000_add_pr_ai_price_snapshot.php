<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pull_request_ai_analyses', function (Blueprint $table): void {
            // Stored estimate and pricing snapshot: later config changes don't rewrite historical figures.
            $table->decimal('estimated_cost_usd', 14, 8)->nullable();
            $table->decimal('input_price_per_million_usd', 12, 6)->nullable();
            $table->decimal('output_price_per_million_usd', 12, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pull_request_ai_analyses', function (Blueprint $table): void {
            $table->dropColumn(['estimated_cost_usd', 'input_price_per_million_usd', 'output_price_per_million_usd']);
        });
    }
};
