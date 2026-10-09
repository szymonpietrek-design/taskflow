<?php

use App\Enums\ReleaseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('version', 32);
            $table->unsignedInteger('version_major');
            $table->unsignedInteger('version_minor');
            $table->unsignedInteger('version_patch');
            $table->string('name', 160)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(ReleaseStatus::DRAFT->value);
            $table->date('planned_for')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'version']);
            $table->index(['project_id', 'version_major', 'version_minor', 'version_patch'], 'releases_version_order');
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
