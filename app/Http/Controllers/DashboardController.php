<?php

namespace App\Http\Controllers;

use App\Enums\MedicationFrequency;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\MigraineScore;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show today's overview: the score and medications recorded for today.
     */
    public function index(Request $request): Response
    {
        $today = now()->toDateString();

        /** @var MigraineScore|null $score */
        $score = $request->user()
            ->migraineScores()
            ->whereDate('date', $today)
            ->first();

        $medicationsTaken = $request->user()
            ->medicationIntakes()
            ->whereDate('date', $today)
            ->with('medication')
            ->get()
            ->map(fn (MedicationIntake $intake): array => [
                'id' => (int) $intake->medication_id,
                'name' => $intake->medication->name,
                'dose' => $intake->medication->doseLabel(),
                'quantity' => $intake->quantity,
            ])
            ->values();

        $medications = $request->user()
            ->medications()
            ->where('frequency', MedicationFrequency::AdHoc)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Medication $medication): array => [
                'id' => $medication->id,
                'name' => $medication->name,
                'dose' => $medication->doseLabel(),
            ]);

        return Inertia::render('Dashboard', [
            'today' => $today,
            'scoreId' => $score?->id,
            'score' => $score?->score,
            'medications' => $medications,
            'medicationsTaken' => $medicationsTaken,
        ]);
    }
}
