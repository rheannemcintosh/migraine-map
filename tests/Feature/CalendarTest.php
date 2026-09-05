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
    $this->get(route('calendar'))->assertRedirect(route('login'));
});

test('the calendar defaults to the current year and includes only that year\'s scores', function () {
    Carbon::setTestNow('2026-06-15');

    $user = User::factory()->create();
    MigraineScore::factory()->for($user)->create(['date' => '2026-03-10', 'score' => 4]);
    MigraineScore::factory()->for($user)->create(['date' => '2025-12-31', 'score' => 7]);
    MigraineScore::factory()->create(['date' => '2026-03-11', 'score' => 9]);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar')
            ->where('year', 2026)
            ->where('today', '2026-06-15')
            ->where('scores', ['2026-03-10' => 4])
        );
});

test('the calendar can be viewed for another year', function () {
    $user = User::factory()->create();
    MigraineScore::factory()->for($user)->create(['date' => '2025-12-31', 'score' => 7]);

    $this->actingAs($user)
        ->get(route('calendar', ['year' => 2025]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar')
            ->where('year', 2025)
            ->where('scores', ['2025-12-31' => 7])
        );
});

test('a score can be saved for a past day', function () {
    Carbon::setTestNow('2026-06-15');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('migraine-scores.store'), ['date' => '2026-06-14', 'score' => 3])
        ->assertRedirect(route('calendar', ['year' => 2026]));

    $this->assertDatabaseHas('migraine_scores', [
        'user_id' => $user->id,
        'score' => 3,
    ]);

    expect($user->migraineScores()->first()->date->toDateString())->toBe('2026-06-14');
});

test('a score can be saved for today', function () {
    Carbon::setTestNow('2026-06-15');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('migraine-scores.store'), ['date' => '2026-06-15', 'score' => 0])
        ->assertSessionHasNoErrors();

    expect($user->migraineScores()->count())->toBe(1);
});

test('a score cannot be saved for a future day', function () {
    Carbon::setTestNow('2026-06-15');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('migraine-scores.store'), ['date' => '2026-06-16', 'score' => 3])
        ->assertSessionHasErrors('date');

    expect($user->migraineScores()->count())->toBe(0);
});

test('a score must be an integer between 0 and 10', function (mixed $score) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('migraine-scores.store'), ['date' => '2020-01-01', 'score' => $score])
        ->assertSessionHasErrors('score');
})->with([-1, 11, 2.5, 'high', null]);

test('only one score can be saved per day', function () {
    $user = User::factory()->create();
    MigraineScore::factory()->for($user)->create(['date' => '2020-01-01', 'score' => 2]);

    $this->actingAs($user)
        ->post(route('migraine-scores.store'), ['date' => '2020-01-01', 'score' => 5])
        ->assertSessionHasErrors('date');

    expect($user->migraineScores()->count())->toBe(1)
        ->and($user->migraineScores()->first()->score)->toBe(2);
});

test('different users can score the same day', function () {
    MigraineScore::factory()->create(['date' => '2020-01-01', 'score' => 2]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('migraine-scores.store'), ['date' => '2020-01-01', 'score' => 5])
        ->assertSessionHasNoErrors();

    expect($user->migraineScores()->count())->toBe(1);
});

test('the calendar lists active ad hoc medications and the days medication was taken', function () {
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

    MedicationIntake::factory()->for($user)->for($ibuprofen)->create(['date' => '2026-03-10', 'quantity' => 2]);
    MedicationIntake::factory()->for($user)->for($ibuprofen)->create(['date' => '2025-12-31', 'quantity' => 1]);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar')
            ->where('medicationDays', ['2026-03-10'])
            ->has('medications', 1)
            ->where('medications.0.id', $ibuprofen->id)
            ->where('medications.0.name', 'Ibuprofen')
            ->where('medications.0.dose', '400 mg')
        );
});

test('medications taken can be saved alongside the score', function () {
    Carbon::setTestNow('2026-06-15');
    $user = User::factory()->create();
    $ibuprofen = Medication::factory()->for($user)->create(['frequency' => MedicationFrequency::AdHoc]);
    $paracetamol = Medication::factory()->for($user)->create(['frequency' => MedicationFrequency::AdHoc]);

    $this->actingAs($user)
        ->post(route('migraine-scores.store'), [
            'date' => '2026-06-14',
            'score' => 6,
            'medications' => [
                ['id' => $ibuprofen->id, 'quantity' => 4],
                ['id' => $paracetamol->id, 'quantity' => 2],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('calendar', ['year' => 2026]));

    expect($user->migraineScores()->count())->toBe(1)
        ->and($user->medicationIntakes()->count())->toBe(2);

    $this->assertDatabaseHas('medication_intakes', [
        'user_id' => $user->id,
        'medication_id' => $ibuprofen->id,
        'quantity' => 4,
    ]);
    $this->assertDatabaseHas('medication_intakes', [
        'user_id' => $user->id,
        'medication_id' => $paracetamol->id,
        'quantity' => 2,
    ]);

    expect($user->medicationIntakes()->first()->date->toDateString())->toBe('2026-06-14');
});

test('a score can still be saved without any medications', function () {
    Carbon::setTestNow('2026-06-15');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('migraine-scores.store'), ['date' => '2026-06-14', 'score' => 3, 'medications' => []])
        ->assertSessionHasNoErrors();

    expect($user->medicationIntakes()->count())->toBe(0);
});

test('only the user\'s own active ad hoc medications can be recorded', function (callable $medication) {
    Carbon::setTestNow('2026-06-15');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('migraine-scores.store'), [
            'date' => '2026-06-14',
            'score' => 3,
            'medications' => [['id' => $medication($user)->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('medications.0.id');

    expect($user->migraineScores()->count())->toBe(0)
        ->and($user->medicationIntakes()->count())->toBe(0);
})->with([
    'another user\'s medication' => [fn (User $user) => Medication::factory()->create(['frequency' => MedicationFrequency::AdHoc])],
    'a scheduled medication' => [fn (User $user) => Medication::factory()->for($user)->create(['frequency' => MedicationFrequency::OnceDaily])],
    'an inactive medication' => [fn (User $user) => Medication::factory()->for($user)->create(['frequency' => MedicationFrequency::AdHoc, 'is_active' => false])],
]);

test('the number taken must be a positive integer', function (mixed $quantity) {
    Carbon::setTestNow('2026-06-15');
    $user = User::factory()->create();
    $medication = Medication::factory()->for($user)->create(['frequency' => MedicationFrequency::AdHoc]);

    $this->actingAs($user)
        ->post(route('migraine-scores.store'), [
            'date' => '2026-06-14',
            'score' => 3,
            'medications' => [['id' => $medication->id, 'quantity' => $quantity]],
        ])
        ->assertSessionHasErrors('medications.0.quantity');

    expect($user->migraineScores()->count())->toBe(0);
})->with([0, -1, 1.5, 'two', null]);

test('the same medication cannot be listed twice for one day', function () {
    Carbon::setTestNow('2026-06-15');
    $user = User::factory()->create();
    $medication = Medication::factory()->for($user)->create(['frequency' => MedicationFrequency::AdHoc]);

    $this->actingAs($user)
        ->post(route('migraine-scores.store'), [
            'date' => '2026-06-14',
            'score' => 3,
            'medications' => [
                ['id' => $medication->id, 'quantity' => 1],
                ['id' => $medication->id, 'quantity' => 2],
            ],
        ])
        ->assertSessionHasErrors('medications.0.id');

    expect($user->medicationIntakes()->count())->toBe(0);
});
