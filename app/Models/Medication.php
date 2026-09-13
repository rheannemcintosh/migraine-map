<?php

namespace App\Models;

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
 * @property MedicationFrequency $frequency
 * @property bool $is_prescription
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, MedicationIngredient> $ingredients
 * @property-read Collection<int, MedicationSchedule> $schedules
 */
#[Fillable(['name', 'frequency', 'is_prescription', 'is_active'])]
class Medication extends Model
{
    /** @use HasFactory<MedicationFactory> */
    use HasFactory;

    /**
     * Whether the medication is made up of more than one active ingredient.
     */
    public function isCompound(): bool
    {
        return $this->ingredients->count() > 1;
    }

    /**
     * The dose of a single-ingredient medication, e.g. "400 mg". Null for a
     * compound medication, whose ingredients are only listed in full.
     */
    public function doseLabel(): ?string
    {
        return $this->isCompound() ? null : $this->ingredients->first()?->doseLabel();
    }

    /**
     * Every ingredient with its dose, e.g. "Aspirin 300 mg, Paracetamol 200 mg".
     */
    public function ingredientsLabel(): string
    {
        return $this->ingredients->map(fn (MedicationIngredient $ingredient): string => $ingredient->label())->implode(', ');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The active ingredients, in the order the user entered them.
     *
     * @return HasMany<MedicationIngredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(MedicationIngredient::class)->orderBy('position');
    }

    /**
     * Replace the ingredients with the given list.
     *
     * @param  array<int, array{name?: string|null, dose_amount: float|int|string, dose_unit: string}>  $ingredients
     */
    public function syncIngredients(array $ingredients): void
    {
        $this->ingredients()->delete();

        foreach (array_values($ingredients) as $position => $ingredient) {
            $this->ingredients()->create([
                'name' => count($ingredients) > 1 ? ($ingredient['name'] ?? null) : null,
                'dose_amount' => $ingredient['dose_amount'],
                'dose_unit' => $ingredient['dose_unit'],
                'position' => $position,
            ]);
        }

        $this->unsetRelation('ingredients');
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
     * Doses that already exist (same time of day or clock time) are kept so
     * their confirmations survive; the rest are removed and new ones created.
     *
     * @param  array<int, array{time_of_day?: string|null, time?: string|null, quantity?: int|null}>  $doses
     */
    public function syncSchedules(array $doses): void
    {
        $existing = $this->schedules()->get();
        $kept = [];

        foreach (array_values($doses) as $position => $dose) {
            $timeOfDay = $dose['time_of_day'] ?? null;
            $time = $dose['time'] ?? null;
            $quantity = $dose['quantity'] ?? 1;

            $match = $existing->first(fn (MedicationSchedule $schedule): bool => ! in_array($schedule->id, $kept, true)
                && $schedule->time_of_day?->value === $timeOfDay
                && $schedule->formattedTime() === ($time === null ? null : substr($time, 0, 5)));

            if ($match !== null) {
                $match->update(['quantity' => $quantity, 'position' => $position]);
                $kept[] = $match->id;

                continue;
            }

            $created = $this->schedules()->create([
                'time_of_day' => $timeOfDay,
                'time' => $time,
                'quantity' => $quantity,
                'position' => $position,
            ]);
            $kept[] = $created->id;
        }

        $this->schedules()->whereNotIn('id', $kept)->delete();

        $this->unsetRelation('schedules');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => MedicationFrequency::class,
            'is_prescription' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
