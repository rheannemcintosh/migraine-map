<?php

namespace App\Models;

use App\Enums\TimeOfDay;
use Database\Factories\MedicationScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
