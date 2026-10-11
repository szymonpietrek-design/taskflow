<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable()->after('generated_at');
            $table->timestamp('published_at')->nullable()->after('edited_at');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn(['published_at', 'edited_at']);
        });
    }
};
