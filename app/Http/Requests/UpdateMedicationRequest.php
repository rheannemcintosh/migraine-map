<?php

namespace App\Http\Requests;

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Models\Medication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMedicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        $medication = $this->route('medication');

        return $medication instanceof Medication
            && $this->user()->is($medication->user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'dose_amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'dose_unit' => ['required', Rule::enum(DoseUnit::class)],
            'frequency' => ['required', Rule::enum(MedicationFrequency::class)],
            'is_prescription' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
