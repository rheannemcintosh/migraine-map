<?php

namespace App\Http\Requests;

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Enums\TimeOfDay;
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
            'schedules' => ['sometimes', 'array'],
            'schedules.*' => ['required', 'array:time_of_day,time'],
            'schedules.*.time_of_day' => ['required_without:schedules.*.time', 'prohibits:schedules.*.time', 'nullable', Rule::enum(TimeOfDay::class)],
            'schedules.*.time' => ['required_without:schedules.*.time_of_day', 'nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'schedules.*.time_of_day' => 'time of day',
            'schedules.*.time' => 'time',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'schedules.*.time_of_day.required_without' => 'Choose a time of day or a specific time for each dose.',
            'schedules.*.time.required_without' => 'Choose a time of day or a specific time for each dose.',
            'schedules.*.time_of_day.prohibits' => 'A dose can have either a time of day or a specific time, not both.',
        ];
    }
}
