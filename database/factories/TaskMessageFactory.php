<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskMessage>
 */
class TaskMessageFactory extends Factory
{
    protected $model = TaskMessage::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }
}
