<?php

namespace Database\Factories;

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medication>
 */
class MedicationFactory extends Factory
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
            'name' => fake()->randomElement(['Sumatriptan', 'Ibuprofen', 'Paracetamol', 'Propranolol', 'Topiramate']),
            'dose_amount' => fake()->randomElement([1, 2, 50, 100, 400]),
            'dose_unit' => fake()->randomElement(DoseUnit::cases()),
            'frequency' => fake()->randomElement(MedicationFrequency::cases()),
            'is_prescription' => fake()->boolean(),
            'is_active' => true,
        ];
    }
}
