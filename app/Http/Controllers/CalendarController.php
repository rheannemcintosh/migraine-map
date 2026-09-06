<?php

namespace App\Http\Controllers;

use App\Enums\MedicationFrequency;
use App\Http\Requests\StoreMigraineScoreRequest;
use App\Http\Requests\UpdateMigraineScoreRequest;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\MedicationSchedule;
use App\Models\MedicationScheduleConfirmation;
use App\Models\MigraineScore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        /** @var array<string, array<int, array{id: int, name: string, dose: string, quantity: int}>> $medicationsByDay */
        $medicationsByDay = $intakesByDay
            ->map(fn ($intakes): array => $intakes
                ->map(fn (MedicationIntake $intake): array => [
                    'id' => (int) $intake->medication_id,
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

        $scheduledDosesByDay = $this->scheduledDosesByDay($request, $year);

        /** @var array<int, string> $missedMedicationDays */
        $missedMedicationDays = collect($scheduledDosesByDay)
            ->filter(fn (array $doses): bool => collect($doses)->contains(fn (array $dose): bool => $dose['confirmationId'] === null))
            ->keys()
            ->all();

        return Inertia::render('Calendar', [
            'year' => $year,
            'today' => now()->toDateString(),
            'scores' => $scores,
            'scoreIds' => $scoreIds,
            'medicationDays' => $medicationDays,
            'medicationsByDay' => $medicationsByDay,
            'medications' => $medications,
            'scheduledDosesByDay' => $scheduledDosesByDay,
            'missedMedicationDays' => $missedMedicationDays,
        ]);
    }

    /**
     * Every scheduled dose due on each day of the year so far, with the id of
     * its confirmation when the user has ticked it off.
     *
     * @return array<string, array<int, array{scheduleId: int, medicationId: int, name: string, dose: string, label: string, confirmationId: int|null}>>
     */
    private function scheduledDosesByDay(Request $request, int $year): array
    {
        $user = $request->user();

        $schedules = MedicationSchedule::query()
            ->whereHas('medication', fn ($query) => $query->where('user_id', $user->id)->where('is_active', true))
            ->with('medication')
            ->get()
            ->sortBy([
                fn (MedicationSchedule $a, MedicationSchedule $b): int => strcmp($a->medication->name, $b->medication->name),
                fn (MedicationSchedule $a, MedicationSchedule $b): int => $a->position <=> $b->position,
            ])
            ->values();

        if ($schedules->isEmpty()) {
            return [];
        }

        $confirmations = $user->medicationScheduleConfirmations()
            ->whereYear('date', $year)
            ->get()
            ->keyBy(fn (MedicationScheduleConfirmation $confirmation): string => $confirmation->medication_schedule_id.'|'.$confirmation->date->toDateString());

        $start = Carbon::create($year)->startOfYear();
        $end = min($start->copy()->endOfYear(), today());

        if ($end->lessThan($start)) {
            return [];
        }

        $dosesByDay = [];

        foreach ($start->daysUntil($end) as $day) {
            $date = $day->toDateString();

            $doses = $schedules
                ->filter(fn (MedicationSchedule $schedule): bool => $schedule->isDueOn($day))
                ->map(fn (MedicationSchedule $schedule): array => [
                    'scheduleId' => $schedule->id,
                    'medicationId' => $schedule->medication_id,
                    'name' => $schedule->medication->name,
                    'dose' => (float) $schedule->medication->dose_amount.' '.$schedule->medication->dose_unit->value,
                    'label' => $schedule->label(),
                    'confirmationId' => $confirmations->get($schedule->id.'|'.$date)?->id,
                ])
                ->values()
                ->all();

            if ($doses !== []) {
                $dosesByDay[$date] = $doses;
            }
        }

        return $dosesByDay;
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
     * Update a logged migraine from the calendar view.
     *
     * The score is always updated. When a medications list is supplied it
     * replaces whatever was recorded for that day; omitting it leaves the
     * existing medications untouched.
     */
    public function update(UpdateMigraineScoreRequest $request, MigraineScore $migraineScore): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $syncsMedications = array_key_exists('medications', $validated);

        DB::transaction(function () use ($user, $migraineScore, $validated, $syncsMedications): void {
            $migraineScore->update(['score' => $validated['score']]);

            if (! $syncsMedications) {
                return;
            }

            $date = $migraineScore->date->toDateString();

            $user->medicationIntakes()->where('date', $date)->delete();

            $user->medicationIntakes()->createMany(
                array_map(fn (array $medication): array => [
                    'medication_id' => $medication['id'],
                    'date' => $date,
                    'quantity' => $medication['quantity'],
                ], $validated['medications']),
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $syncsMedications ? __('Score and medications updated.') : __('Score updated.'),
        ]);

        return to_route('calendar', ['year' => $migraineScore->date->year]);
    }
}
