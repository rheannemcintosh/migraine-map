<?php

namespace Database\Factories;

use App\Enums\TimeOfDay;
use App\Models\Medication;
use App\Models\MedicationSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicationSchedule>
 */
class MedicationScheduleFactory extends Factory
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
            'time_of_day' => fake()->randomElement(TimeOfDay::cases()),
            'time' => null,
            'position' => 0,
        ];
    }

    /**
     * Schedule the dose for a specific clock time instead of a named period.
     */
    public function at(string $time): static
    {
        return $this->state(fn (): array => [
            'time_of_day' => null,
            'time' => $time,
        ]);
    }

    /**
     * Schedule the dose for a named period of the day.
     */
    public function during(TimeOfDay $timeOfDay): static
    {
        return $this->state(fn (): array => [
            'time_of_day' => $timeOfDay,
            'time' => null,
        ]);
    }
}
