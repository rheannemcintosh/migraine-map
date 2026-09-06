<?php

namespace Database\Factories;

use App\Models\Medication;
use App\Models\MedicationSchedule;
use App\Models\MedicationScheduleConfirmation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicationScheduleConfirmation>
 */
class MedicationScheduleConfirmationFactory extends Factory
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
            'medication_schedule_id' => fn (array $attributes): MedicationSchedule => MedicationSchedule::factory()
                ->for(Medication::factory()->create(['user_id' => $attributes['user_id']]))
                ->create(),
            'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }
}
