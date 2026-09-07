<?php

namespace App\Models;

use Database\Factories\MedicationScheduleConfirmationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's confirmation that a single scheduled dose was taken on a given day.
 *
 * @property int $id
 * @property int $user_id
 * @property int $medication_schedule_id
 * @property Carbon $date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read MedicationSchedule $schedule
 */
#[Fillable(['medication_schedule_id', 'date'])]
class MedicationScheduleConfirmation extends Model
{
    /** @use HasFactory<MedicationScheduleConfirmationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MedicationSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MedicationSchedule::class, 'medication_schedule_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'medication_schedule_id' => 'integer',
            'date' => 'date:Y-m-d',
        ];
    }
}
