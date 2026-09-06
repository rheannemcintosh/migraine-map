<?php

namespace Database\Factories;

use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicationIntake>
 */
class MedicationIntakeFactory extends Factory
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
            'medication_id' => fn (array $attributes): Medication => Medication::factory()->create(['user_id' => $attributes['user_id']]),
            'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'quantity' => fake()->numberBetween(1, 4),
        ];
    }
}
