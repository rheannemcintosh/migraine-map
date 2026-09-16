<?php

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Enums\TimeOfDay;
use App\Exports\MigraineCsv;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\MedicationSchedule;
use App\Models\MedicationScheduleConfirmation;
use App\Models\MigraineExport;
use App\Models\MigraineScore;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('medication-exports'))->assertRedirect(route('login'));
});

test('the export tab shows an empty state with default dates when the user has no exports', function () {
    Carbon::setTestNow('2026-09-16 12:00:00');

    $this->actingAs(User::factory()->create())
        ->get(route('medication-exports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MedicationExports')
            ->has('exports', 0)
            ->where('defaultFromDate', '2026-01-01')
            ->where('defaultToDate', '2026-09-16')
        );
});

test('the export tab lists only the user\'s own exports, newest first', function () {
    $user = User::factory()->create();
    $older = MigraineExport::factory()->for($user)->create(['from_date' => '2026-01-01', 'to_date' => '2026-03-31', 'created_at' => '2026-04-01 09:00:00']);
    $newer = MigraineExport::factory()->for($user)->create(['from_date' => '2026-01-01', 'to_date' => '2026-06-30', 'include_regular' => true, 'created_at' => '2026-07-01 09:00:00']);
    MigraineExport::factory()->create();

    $this->actingAs($user)
        ->get(route('medication-exports'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('exports', 2)
            ->where('exports.0.id', $newer->id)
            ->where('exports.0.from_date', '2026-01-01')
            ->where('exports.0.to_date', '2026-06-30')
            ->where('exports.0.include_ad_hoc', true)
            ->where('exports.0.include_regular', true)
            ->where('exports.0.file_name', 'migraine-map-2026-01-01-to-2026-06-30.csv')
            ->where('exports.1.id', $older->id)
        );
});

test('an export can be created and is added to the user\'s list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('medication-exports.store'), [
            'from_date' => '2026-01-01',
            'to_date' => '2026-02-15',
            'include_ad_hoc' => true,
            'include_regular' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('medication-exports'));

    $export = $user->migraineExports()->sole();

    expect($export->from_date->toDateString())->toBe('2026-01-01')
        ->and($export->to_date->toDateString())->toBe('2026-02-15')
        ->and($export->include_ad_hoc)->toBeTrue()
        ->and($export->include_regular)->toBeTrue();
});

test('a from date after the to date is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('medication-exports.store'), [
            'from_date' => '2026-03-01',
            'to_date' => '2026-02-15',
            'include_ad_hoc' => true,
            'include_regular' => false,
        ])
        ->assertSessionHasErrors('to_date');

    expect($user->migraineExports()->count())->toBe(0);
});

test('the dates and medication options are required', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('medication-exports.store'), [])
        ->assertSessionHasErrors(['from_date', 'to_date', 'include_ad_hoc', 'include_regular']);
});

test('an export downloads as a CSV file', function () {
    $user = User::factory()->create();
    $export = MigraineExport::factory()->for($user)->create(['from_date' => '2026-01-01', 'to_date' => '2026-01-31']);
    MigraineScore::factory()->for($user)->create(['date' => '2026-01-05', 'score' => 7]);

    $response = $this->actingAs($user)
        ->get(route('medication-exports.download', $export))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('migraine-map-2026-01-01-to-2026-01-31.csv');

    $lines = explode("\n", trim($response->streamedContent()));

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toBe('Month,'.implode(',', range(1, 31)))
        ->and(str_getcsv($lines[1], escape: '')[0])->toBe('January 2026 - Migraine score')
        ->and(str_getcsv($lines[1], escape: '')[5])->toBe('7')
        ->and(str_getcsv($lines[2], escape: '')[0])->toBe('January 2026 - Medications taken');
});

test('a user cannot download another user\'s export', function () {
    $export = MigraineExport::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('medication-exports.download', $export))
        ->assertForbidden();
});

test('the CSV lays days across the columns and two rows per month, limited to the date range', function () {
    Carbon::setTestNow('2026-03-20 12:00:00');
    $user = User::factory()->create();

    $sumatriptan = Medication::factory()->for($user)->withDose(50, DoseUnit::Milligram)->create(['name' => 'Sumatriptan', 'frequency' => MedicationFrequency::AdHoc]);
    $propranolol = Medication::factory()->for($user)->withDose(40, DoseUnit::Milligram)->create(['name' => 'Propranolol', 'frequency' => MedicationFrequency::OnceDaily, 'created_at' => '2026-01-01']);
    $waking = MedicationSchedule::factory()->for($propranolol)->during(TimeOfDay::Waking)->create(['created_at' => '2026-01-01']);

    MigraineScore::factory()->for($user)->create(['date' => '2026-01-14', 'score' => 3]);
    MigraineScore::factory()->for($user)->create(['date' => '2026-02-28', 'score' => 8]);
    MigraineScore::factory()->for($user)->create(['date' => '2026-03-16', 'score' => 5]);
    MigraineScore::factory()->for($user)->create(['date' => '2026-03-01', 'score' => 9]);

    MedicationIntake::factory()->for($user)->for($sumatriptan)->create(['date' => '2026-02-28', 'quantity' => 2]);
    MedicationIntake::factory()->for($user)->for($sumatriptan)->create(['date' => '2026-03-16', 'quantity' => 1]);
    MedicationScheduleConfirmation::factory()->for($user)->for($waking, 'schedule')->create(['date' => '2026-02-28']);
    MedicationScheduleConfirmation::factory()->for($user)->for($waking, 'schedule')->create(['date' => '2026-03-01']);

    MigraineScore::factory()->create(['date' => '2026-02-10', 'score' => 10]);

    $export = MigraineExport::factory()->for($user)->create([
        'from_date' => '2026-01-10',
        'to_date' => '2026-03-15',
        'include_ad_hoc' => true,
        'include_regular' => true,
    ]);

    $rows = (new MigraineCsv($export))->rows();

    expect($rows)->toHaveCount(7)
        ->and($rows[0])->toBe(['Month', ...array_map(strval(...), range(1, 31))])
        ->and($rows[1][0])->toBe('January 2026 - Migraine score')
        ->and($rows[1][14])->toBe('3')
        ->and($rows[2][0])->toBe('January 2026 - Medications taken')
        ->and($rows[3][0])->toBe('February 2026 - Migraine score')
        ->and($rows[3][28])->toBe('8')
        ->and($rows[3][29])->toBe('')
        ->and($rows[4][28])->toBe('2 x Sumatriptan 50 mg; Propranolol 40 mg (Upon waking)')
        ->and($rows[5][0])->toBe('March 2026 - Migraine score')
        ->and($rows[5][1])->toBe('9')
        ->and($rows[5][16])->toBe('')
        ->and($rows[6][1])->toBe('Propranolol 40 mg (Upon waking)')
        ->and($rows[6][16])->toBe('')
        ->and(collect($rows[3])->contains('10'))->toBeFalse();
});

test('the medications row only includes the kinds of medication that were selected', function () {
    $user = User::factory()->create();

    $sumatriptan = Medication::factory()->for($user)->withDose(50, DoseUnit::Milligram)->create(['name' => 'Sumatriptan']);
    $propranolol = Medication::factory()->for($user)->withDose(40, DoseUnit::Milligram)->create(['name' => 'Propranolol', 'created_at' => '2026-01-01']);
    $waking = MedicationSchedule::factory()->for($propranolol)->during(TimeOfDay::Waking)->create(['created_at' => '2026-01-01']);

    MedicationIntake::factory()->for($user)->for($sumatriptan)->create(['date' => '2026-01-05', 'quantity' => 1]);
    MedicationScheduleConfirmation::factory()->for($user)->for($waking, 'schedule')->create(['date' => '2026-01-05']);

    $adHocOnly = MigraineExport::factory()->for($user)->create(['from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'include_ad_hoc' => true, 'include_regular' => false]);
    $regularOnly = MigraineExport::factory()->for($user)->create(['from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'include_ad_hoc' => false, 'include_regular' => true]);
    $neither = MigraineExport::factory()->for($user)->create(['from_date' => '2026-01-01', 'to_date' => '2026-01-31', 'include_ad_hoc' => false, 'include_regular' => false]);

    expect((new MigraineCsv($adHocOnly))->rows()[2][5])->toBe('Sumatriptan 50 mg')
        ->and((new MigraineCsv($regularOnly))->rows()[2][5])->toBe('Propranolol 40 mg (Upon waking)')
        ->and((new MigraineCsv($neither))->rows()[2][5])->toBe('');
});
