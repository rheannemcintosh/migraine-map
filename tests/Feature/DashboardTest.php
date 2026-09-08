<?php

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Models\Medication;
use App\Models\MedicationIntake;
use App\Models\MigraineScore;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the dashboard shows today\'s date and an empty day when nothing is logged', function () {
    Carbon::setTestNow('2026-06-15');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('today', '2026-06-15')
            ->where('scoreId', null)
            ->where('score', null)
            ->where('medications', [])
            ->where('medicationsTaken', [])
        );
});

test('the dashboard shows today\'s score and medications taken', function () {
    Carbon::setTestNow('2026-06-15');

    $user = User::factory()->create();
    $score = MigraineScore::factory()->for($user)->create(['date' => '2026-06-15', 'score' => 7]);
    $ibuprofen = Medication::factory()->for($user)->create([
        'name' => 'Ibuprofen',
        'dose_amount' => 400,
        'dose_unit' => DoseUnit::Milligram,
        'frequency' => MedicationFrequency::AdHoc,
    ]);
    MedicationIntake::factory()->for($user)->for($ibuprofen)->create(['date' => '2026-06-15', 'quantity' => 2]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('scoreId', $score->id)
            ->where('score', 7)
            ->where('medicationsTaken', [
                ['id' => $ibuprofen->id, 'name' => 'Ibuprofen', 'dose' => '400 mg', 'quantity' => 2],
            ])
        );
});

test('the dashboard only shows today\'s data', function () {
    Carbon::setTestNow('2026-06-15');

    $user = User::factory()->create();
    MigraineScore::factory()->for($user)->create(['date' => '2026-06-14', 'score' => 3]);
    $medication = Medication::factory()->for($user)->create(['frequency' => MedicationFrequency::AdHoc]);
    MedicationIntake::factory()->for($user)->for($medication)->create(['date' => '2026-06-14', 'quantity' => 1]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('score', null)
            ->where('scoreId', null)
            ->where('medicationsTaken', [])
        );
});

test('the dashboard only shows the signed in user\'s day', function () {
    Carbon::setTestNow('2026-06-15');

    MigraineScore::factory()->create(['date' => '2026-06-15', 'score' => 9]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('score', null)
        );
});

test('the dashboard lists the active ad hoc medications that can be recorded', function () {
    Carbon::setTestNow('2026-06-15');

    $user = User::factory()->create();
    $ibuprofen = Medication::factory()->for($user)->create([
        'name' => 'Ibuprofen',
        'dose_amount' => 400,
        'dose_unit' => DoseUnit::Milligram,
        'frequency' => MedicationFrequency::AdHoc,
    ]);
    Medication::factory()->for($user)->create(['name' => 'Propranolol', 'frequency' => MedicationFrequency::OnceDaily]);
    Medication::factory()->for($user)->create(['name' => 'Aspirin', 'frequency' => MedicationFrequency::AdHoc, 'is_active' => false]);
    Medication::factory()->create(['frequency' => MedicationFrequency::AdHoc]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('medications', [
                ['id' => $ibuprofen->id, 'name' => 'Ibuprofen', 'dose' => '400 mg'],
            ])
        );
});

test('the dashboard reflects today\'s data after it is logged', function () {
    Carbon::setTestNow('2026-06-15');

    $user = User::factory()->create();
    $ibuprofen = Medication::factory()->for($user)->create(['frequency' => MedicationFrequency::AdHoc]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('migraine-scores.store'), [
            'date' => '2026-06-15',
            'score' => 5,
            'medications' => [['id' => $ibuprofen->id, 'quantity' => 2]],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('score', 5)
            ->where('medicationsTaken.0.quantity', 2)
        );
});
