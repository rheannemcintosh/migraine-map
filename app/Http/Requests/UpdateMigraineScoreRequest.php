<?php

namespace App\Http\Requests;

use App\Models\MigraineScore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
