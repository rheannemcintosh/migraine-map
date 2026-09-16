<?php

namespace Database\Factories;

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Models\Medication;
use App\Models\MedicationIngredient;
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
            'frequency' => fake()->randomElement(MedicationFrequency::cases()),
            'is_prescription' => fake()->boolean(),
            'is_active' => true,
        ];
    }

    /**
     * Every medication has at least one ingredient; add a random one unless
     * the caller supplied their own.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Medication $medication): void {
            if ($medication->ingredients()->doesntExist()) {
                MedicationIngredient::factory()->for($medication)->create();
            }
        });
    }

    /**
     * A single-ingredient medication with the given dose.
     */
    public function withDose(float|int $amount, DoseUnit $unit): static
    {
        return $this->has(
            MedicationIngredient::factory()->state(['dose_amount' => $amount, 'dose_unit' => $unit]),
            'ingredients',
        );
    }

    /**
     * A compound medication made up of the given named ingredients.
     *
     * @param  array<int, array{name: string, dose_amount: float|int, dose_unit: DoseUnit}>  $ingredients
     */
    public function withIngredients(array $ingredients): static
    {
        $factory = $this;

        foreach (array_values($ingredients) as $position => $ingredient) {
            $factory = $factory->has(
                MedicationIngredient::factory()->state([...$ingredient, 'position' => $position]),
                'ingredients',
            );
        }

        return $factory;
    }
}
