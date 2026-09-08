<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicationConfirmationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'medication_schedule_id' => [
                'required',
                'integer',
                Rule::exists('medication_schedules', 'id')->where(
                    fn ($query) => $query->whereIn('medication_id', $this->user()->medications()->select('id')),
                ),
                Rule::unique('medication_schedule_confirmations', 'medication_schedule_id')
                    ->where('user_id', $this->user()->id)
                    ->where('date', $this->input('date')),
            ],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'medication_schedule_id.exists' => 'Only your own scheduled doses can be confirmed.',
            'medication_schedule_id.unique' => 'This dose has already been confirmed for this day.',
            'date.before_or_equal' => 'Doses cannot be confirmed for future days.',
        ];
    }
}
