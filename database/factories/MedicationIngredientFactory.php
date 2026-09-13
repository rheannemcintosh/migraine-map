<?php

namespace Database\Factories;

use App\Enums\DoseUnit;
use App\Models\Medication;
use App\Models\MedicationIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicationIngredient>
 */
class MedicationIngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medication_id' => Medication::factory(),
            'name' => null,
            'dose_amount' => fake()->randomElement([1, 2, 50, 100, 400]),
            'dose_unit' => fake()->randomElement(DoseUnit::cases()),
            'position' => 0,
        ];
    }
}
