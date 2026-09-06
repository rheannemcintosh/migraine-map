<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedicationConfirmationRequest;
use App\Models\MedicationScheduleConfirmation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MedicationConfirmationController extends Controller
{
    /**
     * Confirm a scheduled dose was taken on a given day.
     */
    public function store(StoreMedicationConfirmationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $confirmation = $request->user()->medicationScheduleConfirmations()->create([
            'medication_schedule_id' => $validated['medication_schedule_id'],
            'date' => $validated['date'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dose confirmed.')]);

        return to_route('calendar', ['year' => $confirmation->date->year]);
    }

    /**
     * Undo a dose confirmation.
     */
    public function destroy(Request $request, MedicationScheduleConfirmation $medicationConfirmation): RedirectResponse
    {
        abort_unless($request->user()->is($medicationConfirmation->user), 403);

        $year = $medicationConfirmation->date->year;

        $medicationConfirmation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dose confirmation removed.')]);

        return to_route('calendar', ['year' => $year]);
    }
}
