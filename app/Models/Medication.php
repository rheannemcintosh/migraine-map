<?php

namespace App\Models;

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use Database\Factories\MedicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $dose_amount
 * @property DoseUnit $dose_unit
 * @property MedicationFrequency $frequency
 * @property bool $is_prescription
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, MedicationSchedule> $schedules
 */
#[Fillable(['name', 'dose_amount', 'dose_unit', 'frequency', 'is_prescription', 'is_active'])]
class Medication extends Model
{
    /** @use HasFactory<MedicationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The scheduled daily doses, in the order the user entered them.
     *
     * @return HasMany<MedicationSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(MedicationSchedule::class)->orderBy('position');
    }

    /**
     * Replace the schedule with the given doses.
     *
     * @param  array<int, array{time_of_day?: string|null, time?: string|null}>  $doses
     */
    public function syncSchedules(array $doses): void
    {
        $this->schedules()->delete();

        $this->schedules()->createMany(collect($doses)->values()->map(fn (array $dose, int $position): array => [
            'time_of_day' => $dose['time_of_day'] ?? null,
            'time' => $dose['time'] ?? null,
            'position' => $position,
        ])->all());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dose_amount' => 'decimal:3',
            'dose_unit' => DoseUnit::class,
            'frequency' => MedicationFrequency::class,
            'is_prescription' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
