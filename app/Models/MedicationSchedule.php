<?php

namespace App\Models;

use App\Enums\TimeOfDay;
use Database\Factories\MedicationScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single scheduled daily dose of a medication, expressed either as a named
 * period of the day or as a specific clock time.
 *
 * @property int $id
 * @property int $medication_id
 * @property TimeOfDay|null $time_of_day
 * @property string|null $time
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Medication $medication
 * @property-read Collection<int, MedicationScheduleConfirmation> $confirmations
 */
#[Fillable(['time_of_day', 'time', 'position'])]
class MedicationSchedule extends Model
{
    /** @use HasFactory<MedicationScheduleFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Medication, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    /**
     * @return HasMany<MedicationScheduleConfirmation, $this>
     */
    public function confirmations(): HasMany
    {
        return $this->hasMany(MedicationScheduleConfirmation::class);
    }

    /**
     * Whether this dose is due on the given day: from the day it was scheduled
     * up to today, while the medication is active.
     */
    public function isDueOn(Carbon $date): bool
    {
        return $this->medication->is_active
            && $this->created_at !== null
            && $date->between($this->created_at->copy()->startOfDay(), today()->endOfDay());
    }

    /**
     * The clock time as `HH:MM`, or null for a named period.
     */
    public function formattedTime(): ?string
    {
        return $this->time === null ? null : substr($this->time, 0, 5);
    }

    /**
     * Human-readable description of when the dose is due.
     */
    public function label(): string
    {
        return $this->time_of_day?->label() ?? (string) $this->formattedTime();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_of_day' => TimeOfDay::class,
            'position' => 'integer',
        ];
    }
}
