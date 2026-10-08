<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskType;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Task $task): void {
            if ($task->status_id !== null) {
                return;
            }

            $task->status_id = TaskStatus::factory()->create([
                'project_id' => $task->project_id,
            ])->id;
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_id' => null,
            'number' => fake()->unique()->numberBetween(1, 1_000_000),
            'title' => fake()->sentence(5),
            'description' => fake()->optional()->paragraph(),
            'type' => TaskType::TASK->value,
            'priority' => TaskPriority::NORMAL->value,
            'status_id' => null,
            'assignee_id' => null,
            'created_by' => User::factory(),
            'position' => 1000,
            'due_date' => null,
            'completed_at' => null,
        ];
    }
}
