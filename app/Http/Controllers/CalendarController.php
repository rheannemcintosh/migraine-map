<?php

namespace App\Http\Controllers;

use App\Enums\MedicationFrequency;
use App\Http\Requests\StoreMigraineScoreRequest;
use App\Models\Medication;
use App\Models\MigraineScore;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Show the year-at-a-glance calendar with the user's migraine scores.
     */
    public function index(Request $request, ?int $year = null): Response
    {
        $year ??= now()->year;

        /** @var array<string, int> $scores */
        $scores = $request->user()
            ->migraineScores()
            ->whereYear('date', $year)
            ->pluck('score', 'date')
            ->mapWithKeys(fn (int $score, string $date): array => [
                substr($date, 0, 10) => $score,
            ])
            ->all();

        /** @var array<int, string> $medicationDays */
        $medicationDays = $request->user()
            ->medicationIntakes()
            ->whereYear('date', $year)
            ->distinct()
            ->pluck('date')
            ->map(fn (CarbonInterface $date): string => $date->toDateString())
            ->values()
            ->all();

        $medications = $request->user()
            ->medications()
            ->where('frequency', MedicationFrequency::AdHoc)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Medication $medication): array => [
                'id' => $medication->id,
                'name' => $medication->name,
                'dose' => (float) $medication->dose_amount.' '.$medication->dose_unit->value,
            ]);

        return Inertia::render('Calendar', [
            'year' => $year,
            'today' => now()->toDateString(),
            'scores' => $scores,
            'medicationDays' => $medicationDays,
            'medications' => $medications,
        ]);
    }

    /**
     * Save a migraine score, and any medications taken, for a single day.
     */
    public function store(StoreMigraineScoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        /** @var array<int, array{id: int, quantity: int}> $medications */
        $medications = $validated['medications'] ?? [];

        /** @var MigraineScore $score */
        $score = DB::transaction(function () use ($user, $validated, $medications): MigraineScore {
            $score = $user->migraineScores()->create([
                'date' => $validated['date'],
                'score' => $validated['score'],
            ]);

            $user->medicationIntakes()->createMany(
                array_map(fn (array $medication): array => [
                    'medication_id' => $medication['id'],
                    'date' => $validated['date'],
                    'quantity' => $medication['quantity'],
                ], $medications),
            );

            return $score;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $medications === [] ? __('Score saved.') : __('Score and medications saved.'),
        ]);

        return to_route('calendar', ['year' => $score->date->year]);
    }
}
