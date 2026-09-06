<?php

namespace App\Http\Requests;

use App\Enums\MedicationFrequency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMigraineScoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
                Rule::unique('migraine_scores', 'date')->where('user_id', $this->user()->id),
            ],
            'score' => ['required', 'integer', 'min:0', 'max:10'],
            'medications' => ['sometimes', 'array'],
            'medications.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('medications', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('frequency', MedicationFrequency::AdHoc->value)
                    ->where('is_active', true),
            ],
            'medications.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.unique' => 'A score has already been logged for this day.',
            'date.before_or_equal' => 'Scores cannot be logged for future days.',
            'medications.*.id.exists' => 'Only your active ad hoc medications can be recorded.',
            'medications.*.id.distinct' => 'Each medication can only be listed once per day.',
            'medications.*.quantity.min' => 'The number taken must be at least 1.',
        ];
    }
}
