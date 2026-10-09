<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('release_changelogs', function (Blueprint $table): void {
            // Existing manually written changelogs have unknown languages.
            $table->string('draft_locale', 35)->nullable()->after('draft_body');
            $table->string('published_locale', 35)->nullable()->after('published_body');
        });
    }

    public function down(): void
    {
        Schema::table('release_changelogs', function (Blueprint $table): void {
            $table->dropColumn(['draft_locale', 'published_locale']);
        });
    }
};
