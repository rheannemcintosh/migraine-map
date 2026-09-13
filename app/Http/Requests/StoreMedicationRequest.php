<?php

namespace App\Http\Requests;

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Enums\TimeOfDay;
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
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*' => ['required', 'array:name,dose_amount,dose_unit'],
            'ingredients.*.name' => [Rule::requiredIf(fn (): bool => count($this->input('ingredients', [])) > 1), 'nullable', 'string', 'max:255'],
            'ingredients.*.dose_amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'ingredients.*.dose_unit' => ['required', Rule::enum(DoseUnit::class)],
            'frequency' => ['required', Rule::enum(MedicationFrequency::class)],
            'is_prescription' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'schedules' => ['sometimes', 'array'],
            'schedules.*' => ['required', 'array:time_of_day,time,quantity'],
            'schedules.*.time_of_day' => ['required_without:schedules.*.time', 'prohibits:schedules.*.time', 'nullable', Rule::enum(TimeOfDay::class)],
            'schedules.*.time' => ['required_without:schedules.*.time_of_day', 'nullable', 'date_format:H:i'],
            'schedules.*.quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
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
            'ingredients.*.name' => 'ingredient name',
            'ingredients.*.dose_amount' => 'dose amount',
            'ingredients.*.dose_unit' => 'dose unit',
            'schedules.*.time_of_day' => 'time of day',
            'schedules.*.time' => 'time',
            'schedules.*.quantity' => 'quantity',
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
            'ingredients.required' => 'Enter at least one dose.',
            'ingredients.*.name.required' => 'Name each ingredient of a compound medication.',
            'schedules.*.time_of_day.required_without' => 'Choose a time of day or a specific time for each dose.',
            'schedules.*.time.required_without' => 'Choose a time of day or a specific time for each dose.',
            'schedules.*.time_of_day.prohibits' => 'A dose can have either a time of day or a specific time, not both.',
        ];
    }
}
