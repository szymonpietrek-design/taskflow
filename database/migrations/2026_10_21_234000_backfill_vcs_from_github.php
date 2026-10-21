<?php

use App\Integrations\Vcs\BackfillGitHubLegacyData;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(BackfillGitHubLegacyData::class)->run();
    }

    public function down(): void
    {
        // Deliberately do not remove copied data: this is an additive migration.
        // The schema migration owns only the generic tables and can be rolled
        // back independently, without altering original github_* records.
    }
};
