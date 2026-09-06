<?php

namespace App\Http\Requests;

use App\Enums\MedicationFrequency;
use App\Models\MigraineScore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMigraineScoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        $migraineScore = $this->route('migraineScore');

        return $migraineScore instanceof MigraineScore
            && $this->user()->is($migraineScore->user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'medications.*.id.exists' => 'Only your active ad hoc medications can be recorded.',
            'medications.*.id.distinct' => 'Each medication can only be listed once per day.',
            'medications.*.quantity.min' => 'The number taken must be at least 1.',
        ];
    }
}
