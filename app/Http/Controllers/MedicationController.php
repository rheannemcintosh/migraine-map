<?php

namespace App\Http\Controllers;

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Http\Requests\StoreMedicationRequest;
use App\Models\Medication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MedicationController extends Controller
{
    /**
     * List the user's declared medications.
     */
    public function index(Request $request): Response
    {
        $medications = $request->user()
            ->medications()
            ->orderBy('name')
            ->get()
            ->map(fn (Medication $medication): array => [
                'id' => $medication->id,
                'name' => $medication->name,
                'dose_amount' => (float) $medication->dose_amount,
                'dose_unit' => $medication->dose_unit->value,
                'frequency' => $medication->frequency->label(),
                'is_prescription' => $medication->is_prescription,
                'is_active' => $medication->is_active,
            ]);

        return Inertia::render('Medications', [
            'medications' => $medications,
            'doseUnits' => DoseUnit::values(),
            'frequencies' => MedicationFrequency::options(),
        ]);
    }

    /**
     * Declare a new medication.
     */
    public function store(StoreMedicationRequest $request): RedirectResponse
    {
        $request->user()->medications()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Medication added.')]);

        return to_route('medications');
    }
}
