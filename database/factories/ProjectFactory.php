<?php

namespace Database\Factories;

use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'name' => Str::headline($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'key' => strtoupper(fake()->unique()->lexify('???')),
            'description' => fake()->optional()->sentence(),
            'type' => ProjectType::STANDARD->value,
            'visibility' => ProjectVisibility::WORKSPACE->value,
            'task_sequence' => 0,
            'archived_at' => null,
        ];
    }
}
