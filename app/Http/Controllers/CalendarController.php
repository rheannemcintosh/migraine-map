<?php

namespace App\Http\Controllers;

use App\Enums\MedicationFrequency;
use App\Http\Requests\StoreMigraineScoreRequest;
use App\Http\Requests\UpdateMigraineScoreRequest;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\MigraineScore;
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

        /** @var array<string, int> $scoreIds */
        $scoreIds = $request->user()
            ->migraineScores()
            ->whereYear('date', $year)
            ->pluck('id', 'date')
            ->mapWithKeys(fn (int $id, string $date): array => [
                substr($date, 0, 10) => $id,
            ])
            ->all();

        $intakesByDay = $request->user()
            ->medicationIntakes()
            ->whereYear('date', $year)
            ->with('medication')
            ->get()
            ->groupBy(fn (MedicationIntake $intake): string => $intake->date->toDateString());

        /** @var array<int, string> $medicationDays */
        $medicationDays = $intakesByDay->keys()->all();

        /** @var array<string, array<int, array{name: string, dose: string, quantity: int}>> $medicationsByDay */
        $medicationsByDay = $intakesByDay
            ->map(fn ($intakes): array => $intakes
                ->map(fn (MedicationIntake $intake): array => [
                    'name' => $intake->medication->name,
                    'dose' => (float) $intake->medication->dose_amount.' '.$intake->medication->dose_unit->value,
                    'quantity' => $intake->quantity,
                ])
                ->values()
                ->all())
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
            'scoreIds' => $scoreIds,
            'medicationDays' => $medicationDays,
            'medicationsByDay' => $medicationsByDay,
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

    /**
     * Update the score of a migraine that has already been logged.
     *
     * Only the score can change here; any medications recorded against the
     * day are left untouched.
     */
    public function update(UpdateMigraineScoreRequest $request, MigraineScore $migraineScore): RedirectResponse
    {
        $migraineScore->update(['score' => $request->validated('score')]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Score updated.'),
        ]);

        return to_route('calendar', ['year' => $migraineScore->date->year]);
    }
}
