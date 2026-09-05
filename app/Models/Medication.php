<?php

namespace App\Models;

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use Database\Factories\MedicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
