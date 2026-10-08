<?php

namespace Database\Factories;

use App\Enums\TaskStatusCategory;
use App\Models\Project;
use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaskStatus>
 */
class TaskStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'project_id' => Project::factory(),
            'name' => Str::headline($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'category' => TaskStatusCategory::TODO->value,
            'position' => 1000,
        ];
    }
}
