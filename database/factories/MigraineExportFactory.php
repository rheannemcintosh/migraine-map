<?php

namespace Database\Factories;

use App\Models\MigraineExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MigraineExport>
 */
class MigraineExportFactory extends Factory
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
            'from_date' => now()->startOfYear()->toDateString(),
            'to_date' => now()->toDateString(),
            'include_ad_hoc' => true,
            'include_regular' => false,
        ];
    }
}
