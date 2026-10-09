<?php

namespace Database\Factories;

use App\Enums\ProjectType;
use App\Enums\ReleaseStatus;
use App\Models\Project;
use App\Models\Release;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Release> */
class ReleaseFactory extends Factory
{
    public function definition(): array
    {
        $patch = fake()->unique()->numberBetween(0, 100000);

        return [
            'project_id' => Project::factory()->state(['type' => ProjectType::APPLICATION]),
            'created_by' => User::factory(),
            'version' => '1.0.'.$patch,
            'version_major' => 1,
            'version_minor' => 0,
            'version_patch' => $patch,
            'name' => fake()->optional()->words(2, true),
            'description' => null,
            'status' => ReleaseStatus::DRAFT,
            'planned_for' => null,
            'released_at' => null,
        ];
    }
}
