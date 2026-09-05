<?php

namespace Database\Factories;

use App\Models\MigraineScore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MigraineScore>
 */
class MigraineScoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->unique()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'score' => fake()->numberBetween(0, 10),
        ];
    }
}
