<?php

namespace App\Http\Controllers;

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Enums\TimeOfDay;
use App\Http\Requests\StoreMedicationRequest;
use App\Http\Requests\UpdateMedicationRequest;
use App\Models\Medication;
use App\Models\MedicationIngredient;
use App\Models\MedicationSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->with(['ingredients', 'schedules'])
            ->orderBy('name')
            ->get()
            ->map(fn (Medication $medication): array => [
                'id' => $medication->id,
                'name' => $medication->name,
                'dose' => $medication->ingredientsLabel(),
                'ingredients' => $medication->ingredients
                    ->map(fn (MedicationIngredient $ingredient): array => [
                        'id' => $ingredient->id,
                        'name' => $ingredient->name,
                        'dose_amount' => (float) $ingredient->dose_amount,
                        'dose_unit' => $ingredient->dose_unit->value,
                    ])
                    ->values()
                    ->all(),
                'frequency' => $medication->frequency->value,
                'is_prescription' => $medication->is_prescription,
                'is_active' => $medication->is_active,
                'schedules' => $medication->schedules
                    ->map(fn (MedicationSchedule $schedule): array => [
                        'id' => $schedule->id,
                        'time_of_day' => $schedule->time_of_day?->value,
                        'time' => $schedule->formattedTime(),
                        'quantity' => $schedule->quantity,
                        'label' => $schedule->label(),
                    ])
                    ->values()
                    ->all(),
            ]);

        return Inertia::render('Medications', [
            'medications' => $medications,
            'doseUnits' => DoseUnit::values(),
            'frequencies' => MedicationFrequency::options(),
            'timesOfDay' => TimeOfDay::options(),
        ]);
    }

    /**
     * Declare a new medication.
     */
    public function store(StoreMedicationRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $medication = $request->user()->medications()->create($request->safe()->except(['ingredients', 'schedules']));

            $medication->syncIngredients($request->validated('ingredients'));
            $medication->syncSchedules($request->validated('schedules', []));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Medication added.')]);

        return to_route('medications');
    }

    /**
     * Update one of the user's medications from the medication page.
     */
    public function update(UpdateMedicationRequest $request, Medication $medication): RedirectResponse
    {
        DB::transaction(function () use ($request, $medication): void {
            $medication->update($request->safe()->except(['ingredients', 'schedules']));

            $medication->syncIngredients($request->validated('ingredients'));

            if ($request->has('schedules')) {
                $medication->syncSchedules($request->validated('schedules'));
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Medication updated.')]);

        return to_route('medications');
    }
}
