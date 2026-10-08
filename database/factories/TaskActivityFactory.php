<?php

namespace Database\Factories;

use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskActivity>
 */
class TaskActivityFactory extends Factory
{
    protected $model = TaskActivity::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'type' => TaskActivityType::STATUS_CHANGED,
            'data' => [
                'from' => ['id' => 1, 'name' => 'Todo', 'category' => 1],
                'to' => ['id' => 2, 'name' => 'In Progress', 'category' => 2],
            ],
        ];
    }
}
