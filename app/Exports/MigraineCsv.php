<?php

namespace App\Exports;

use App\Models\MedicationIntake;
use App\Models\MedicationScheduleConfirmation;
use App\Models\MigraineExport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Lays a user's migraine diary out as a CSV grid: the days of the month run
 * across the columns and each month in the range takes two rows, one for the
 * migraine score and one for the medications taken.
 */
class MigraineCsv
{
    public function __construct(private readonly MigraineExport $export) {}

    /**
     * The CSV document as a string.
     */
    public function toString(): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Could not open a buffer for the export.');
        }

        foreach ($this->rows() as $row) {
            fputcsv($handle, $row, escape: '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * Every row of the grid, starting with the header of day numbers.
     *
     * @return array<int, array<int, string>>
     */
    public function rows(): array
    {
        $from = $this->export->from_date->toImmutable()->startOfDay();
        $to = $this->export->to_date->toImmutable()->startOfDay();

        $scores = $this->scores($from, $to);
        $medications = $this->medications($from, $to);

        $rows = [['Month', ...array_map(strval(...), range(1, 31))]];

        for ($month = $from->startOfMonth(); $month->lessThanOrEqualTo($to); $month = $month->addMonthNoOverflow()) {
            $label = $month->format('F Y');
            $scoreRow = [$label.' - Migraine score'];
            $medicationRow = [$label.' - Medications taken'];

            for ($day = 1; $day <= 31; $day++) {
                if ($day > $month->daysInMonth) {
                    $scoreRow[] = '';
                    $medicationRow[] = '';

                    continue;
                }

                $date = $month->day($day);
                $key = $date->toDateString();

                if ($date->lessThan($from) || $date->greaterThan($to)) {
                    $scoreRow[] = '';
                    $medicationRow[] = '';

                    continue;
                }

                $scoreRow[] = array_key_exists($key, $scores) ? (string) $scores[$key] : '';
                $medicationRow[] = $medications->get($key, collect())->implode('; ');
            }

            $rows[] = $scoreRow;
            $rows[] = $medicationRow;
        }

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    private function scores(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->export->user
            ->migraineScores()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->mapWithKeys(fn ($score): array => [$score->date->toDateString() => $score->score])
            ->all();
    }

    /**
     * The medications taken on each day, ad-hoc intakes first, in the order
     * they were recorded.
     *
     * @return Collection<string, Collection<int, string>>
     */
    private function medications(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        /** @var Collection<string, Collection<int, string>> $byDay */
        $byDay = collect();

        if ($this->export->include_ad_hoc) {
            $this->export->user
                ->medicationIntakes()
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->with('medication.ingredients')
                ->orderBy('date')
                ->orderBy('id')
                ->get()
                ->each(function (MedicationIntake $intake) use ($byDay): void {
                    $label = $this->withDose($intake->medication->name, $intake->medication->doseLabel());
                    $label = $intake->quantity > 1 ? $intake->quantity.' x '.$label : $label;

                    $byDay->getOrPut($intake->date->toDateString(), fn () => collect())->push($label);
                });
        }

        if ($this->export->include_regular) {
            $this->export->user
                ->medicationScheduleConfirmations()
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->with('schedule.medication.ingredients')
                ->orderBy('date')
                ->orderBy('id')
                ->get()
                ->each(function (MedicationScheduleConfirmation $confirmation) use ($byDay): void {
                    $schedule = $confirmation->schedule;
                    $label = $this->withDose($schedule->medication->name, $schedule->doseLabel()).' ('.$schedule->label().')';

                    $byDay->getOrPut($confirmation->date->toDateString(), fn () => collect())->push($label);
                });
        }

        return $byDay;
    }

    private function withDose(string $name, ?string $dose): string
    {
        return $dose === null ? $name : $name.' '.$dose;
    }
}
