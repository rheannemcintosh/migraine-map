<?php

namespace App\Models;

use App\Enums\DoseUnit;
use Database\Factories\MedicationIngredientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One active ingredient of a medication and its dose per unit, e.g. the
 * "Paracetamol 200 mg" in an Anadin Extra tablet.
 *
 * @property int $id
 * @property int $medication_id
 * @property string|null $name
 * @property string $dose_amount
 * @property DoseUnit $dose_unit
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Medication $medication
 */
#[Fillable(['name', 'dose_amount', 'dose_unit', 'position'])]
class MedicationIngredient extends Model
{
    /** @use HasFactory<MedicationIngredientFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Medication, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    /**
     * The dose as displayed to the user, e.g. "400 mg".
     */
    public function doseLabel(): string
    {
        return (float) $this->dose_amount.' '.$this->dose_unit->value;
    }

    /**
     * The ingredient with its dose, e.g. "Paracetamol 200 mg", or just the
     * dose when the ingredient is unnamed.
     */
    public function label(): string
    {
        return $this->name === null ? $this->doseLabel() : $this->name.' '.$this->doseLabel();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dose_amount' => 'decimal:3',
            'dose_unit' => DoseUnit::class,
            'position' => 'integer',
        ];
    }
}
