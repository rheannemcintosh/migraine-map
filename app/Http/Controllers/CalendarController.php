<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMigraineScoreRequest;
use App\Models\MigraineScore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('Calendar', [
            'year' => $year,
            'today' => now()->toDateString(),
            'scores' => $scores,
        ]);
    }

    /**
     * Save a migraine score for a single day.
     */
    public function store(StoreMigraineScoreRequest $request): RedirectResponse
    {
        /** @var MigraineScore $score */
        $score = $request->user()->migraineScores()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Score saved.')]);

        return to_route('calendar', ['year' => $score->date->year]);
    }
}
