<?php

namespace App\Http\Requests;

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicationRequest extends FormRequest
{
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
