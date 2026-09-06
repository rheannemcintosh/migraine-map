<?php

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Enums\TimeOfDay;
use App\Models\Medication;
use App\Models\MedicationSchedule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('medications'))->assertRedirect(route('login'));
});

test('the medications page lists only the user\'s medications with the form options', function () {
    $user = User::factory()->create();
    Medication::factory()->for($user)->create([
        'name' => 'Sumatriptan',
        'dose_amount' => 50,
        'dose_unit' => DoseUnit::Milligram,
        'frequency' => MedicationFrequency::AdHoc,
        'is_prescription' => true,
        'is_active' => true,
    ]);
    Medication::factory()->create(['name' => 'Someone else\'s']);

    $this->actingAs($user)
        ->get(route('medications'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Medications')
            ->has('medications', 1, fn (Assert $medication) => $medication
                ->where('name', 'Sumatriptan')
                ->where('dose_amount', 50)
                ->where('dose_unit', 'mg')
                ->where('frequency', 'ad_hoc')
                ->where('is_prescription', true)
                ->where('is_active', true)
                ->etc()
            )
            ->where('doseUnits', DoseUnit::values())
            ->where('frequencies', MedicationFrequency::options())
        );
});

test('the medications page shows an empty list when nothing has been declared', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('medications'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Medications')
            ->has('medications', 0)
        );
});

test('a medication can be declared', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('medications.store'), [
            'name' => 'Anadin Extra',
            'dose_amount' => 1,
            'dose_unit' => 'tablet',
            'frequency' => 'ad_hoc',
            'is_prescription' => false,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('medications'));

    $this->assertDatabaseHas('medications', [
        'user_id' => $user->id,
        'name' => 'Anadin Extra',
        'dose_unit' => 'tablet',
        'frequency' => 'ad_hoc',
        'is_prescription' => false,
        'is_active' => true,
    ]);

    expect((float) $user->medications()->first()->dose_amount)->toBe(1.0);
});

test('a medication can be declared with a decimal dose', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('medications.store'), [
            'name' => 'Rizatriptan',
            'dose_amount' => 2.5,
            'dose_unit' => 'mg',
            'frequency' => 'twice_daily',
            'is_prescription' => true,
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors();

    expect((float) $user->medications()->first()->dose_amount)->toBe(2.5);
});

test('name and dose are required', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('medications.store'), [
            'dose_unit' => 'mg',
            'frequency' => 'ad_hoc',
            'is_prescription' => false,
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['name', 'dose_amount']);

    expect(Medication::count())->toBe(0);
});

test('the dose must be a positive number', function (mixed $dose) {
    $this->actingAs(User::factory()->create())
        ->post(route('medications.store'), [
            'name' => 'Ibuprofen',
            'dose_amount' => $dose,
            'dose_unit' => 'mg',
            'frequency' => 'ad_hoc',
            'is_prescription' => false,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('dose_amount');
})->with([0, -1, 'lots']);

test('the dose unit and frequency must be from the preset lists', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('medications.store'), [
            'name' => 'Ibuprofen',
            'dose_amount' => 400,
            'dose_unit' => 'handful',
            'frequency' => 'whenever',
            'is_prescription' => false,
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['dose_unit', 'frequency']);
});

test('prescription and active flags must be booleans', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('medications.store'), [
            'name' => 'Ibuprofen',
            'dose_amount' => 400,
            'dose_unit' => 'mg',
            'frequency' => 'ad_hoc',
            'is_prescription' => 'maybe',
            'is_active' => 'yes',
        ])
        ->assertSessionHasErrors(['is_prescription', 'is_active']);
});

test('a medication can be edited from the medication page', function () {
    $user = User::factory()->create();
    $medication = Medication::factory()->for($user)->create([
        'name' => 'Sumatriptan',
        'dose_amount' => 50,
        'dose_unit' => DoseUnit::Milligram,
        'frequency' => MedicationFrequency::AdHoc,
        'is_prescription' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('medications.update', $medication), [
            'name' => 'Sumatriptan',
            'dose_amount' => 100,
            'dose_unit' => 'mg',
            'frequency' => 'twice_daily',
            'is_prescription' => true,
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('medications'));

    $medication->refresh();

    expect((float) $medication->dose_amount)->toBe(100.0)
        ->and($medication->frequency)->toBe(MedicationFrequency::TwiceDaily)
        ->and($medication->is_active)->toBeFalse();
});

test('a medication edit is validated', function () {
    $user = User::factory()->create();
    $medication = Medication::factory()->for($user)->create(['name' => 'Sumatriptan']);

    $this->actingAs($user)
        ->patch(route('medications.update', $medication), [
            'name' => '',
            'dose_amount' => 0,
            'dose_unit' => 'handful',
            'frequency' => 'whenever',
            'is_prescription' => false,
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['name', 'dose_amount', 'dose_unit', 'frequency']);

    expect($medication->fresh()->name)->toBe('Sumatriptan');
});

test('a user cannot edit another user\'s medication', function () {
    $medication = Medication::factory()->create(['name' => 'Theirs']);

    $this->actingAs(User::factory()->create())
        ->patch(route('medications.update', $medication), [
            'name' => 'Mine now',
            'dose_amount' => 1,
            'dose_unit' => 'tablet',
            'frequency' => 'ad_hoc',
            'is_prescription' => false,
            'is_active' => true,
        ])
        ->assertForbidden();

    expect($medication->fresh()->name)->toBe('Theirs');
});

test('guests cannot edit a medication', function () {
    $medication = Medication::factory()->create();

    $this->patch(route('medications.update', $medication), [])
        ->assertRedirect(route('login'));
});

test('the medications page includes each medication\'s schedule in order', function () {
    $user = User::factory()->create();
    $medication = Medication::factory()->for($user)->create(['name' => 'Propranolol']);
    MedicationSchedule::factory()->for($medication)->at('21:30')->create(['position' => 1]);
    MedicationSchedule::factory()->for($medication)->during(TimeOfDay::Waking)->create(['position' => 0]);

    $this->actingAs($user)
        ->get(route('medications'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Medications')
            ->has('medications', 1, fn (Assert $medication) => $medication
                ->where('name', 'Propranolol')
                ->has('schedules', 2)
                ->where('schedules.0.time_of_day', 'waking')
                ->where('schedules.0.time', null)
                ->where('schedules.0.label', 'Upon waking')
                ->where('schedules.1.time_of_day', null)
                ->where('schedules.1.time', '21:30')
                ->where('schedules.1.label', '21:30')
                ->etc()
            )
            ->where('timesOfDay', TimeOfDay::options())
        );
});

test('a medication without a schedule is listed with an empty schedule', function () {
    $user = User::factory()->create();
    Medication::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('medications'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('medications.0.schedules', 0)
        );
});

test('a medication can be declared with a single night-time dose', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('medications.store'), [
            'name' => 'Amitriptyline',
            'dose_amount' => 10,
            'dose_unit' => 'mg',
            'frequency' => 'once_daily',
            'is_prescription' => true,
            'is_active' => true,
            'schedules' => [
                ['time_of_day' => 'night', 'time' => null],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('medications'));

    $medication = $user->medications()->firstOrFail();

    expect($medication->schedules)->toHaveCount(1)
        ->and($medication->schedules[0]->time_of_day)->toBe(TimeOfDay::Night)
        ->and($medication->schedules[0]->time)->toBeNull()
        ->and($medication->schedules[0]->position)->toBe(0);
});

test('a medication can be declared with multiple doses per day', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('medications.store'), [
            'name' => 'Propranolol',
            'dose_amount' => 40,
            'dose_unit' => 'mg',
            'frequency' => 'three_times_daily',
            'is_prescription' => true,
            'is_active' => true,
            'schedules' => [
                ['time_of_day' => 'waking', 'time' => null],
                ['time_of_day' => null, 'time' => '14:00'],
                ['time_of_day' => 'bedtime', 'time' => null],
            ],
        ])
        ->assertSessionHasNoErrors();

    $schedules = $user->medications()->firstOrFail()->schedules;

    expect($schedules)->toHaveCount(3)
        ->and($schedules->pluck('position')->all())->toBe([0, 1, 2])
        ->and($schedules[0]->time_of_day)->toBe(TimeOfDay::Waking)
        ->and($schedules[1]->time_of_day)->toBeNull()
        ->and($schedules[1]->formattedTime())->toBe('14:00')
        ->and($schedules[2]->time_of_day)->toBe(TimeOfDay::Bedtime);
});

test('a medication can be declared without a schedule', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('medications.store'), [
            'name' => 'Ibuprofen',
            'dose_amount' => 400,
            'dose_unit' => 'mg',
            'frequency' => 'ad_hoc',
            'is_prescription' => false,
            'is_active' => true,
            'schedules' => [],
        ])
        ->assertSessionHasNoErrors();

    expect($user->medications()->firstOrFail()->schedules)->toHaveCount(0);
});

test('each scheduled dose needs a time of day or a specific time', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('medications.store'), [
            'name' => 'Ibuprofen',
            'dose_amount' => 400,
            'dose_unit' => 'mg',
            'frequency' => 'once_daily',
            'is_prescription' => false,
            'is_active' => true,
            'schedules' => [
                ['time_of_day' => null, 'time' => null],
            ],
        ])
        ->assertSessionHasErrors(['schedules.0.time_of_day', 'schedules.0.time']);

    expect(Medication::count())->toBe(0);
});

test('a scheduled dose cannot have both a time of day and a specific time', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('medications.store'), [
            'name' => 'Ibuprofen',
            'dose_amount' => 400,
            'dose_unit' => 'mg',
            'frequency' => 'once_daily',
            'is_prescription' => false,
            'is_active' => true,
            'schedules' => [
                ['time_of_day' => 'night', 'time' => '22:00'],
            ],
        ])
        ->assertSessionHasErrors(['schedules.0.time_of_day']);

    expect(Medication::count())->toBe(0);
});

test('scheduled doses are validated against the preset periods and time format', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('medications.store'), [
            'name' => 'Ibuprofen',
            'dose_amount' => 400,
            'dose_unit' => 'mg',
            'frequency' => 'once_daily',
            'is_prescription' => false,
            'is_active' => true,
            'schedules' => [
                ['time_of_day' => 'teatime', 'time' => null],
                ['time_of_day' => null, 'time' => '9pm'],
            ],
        ])
        ->assertSessionHasErrors(['schedules.0.time_of_day', 'schedules.1.time']);

    expect(Medication::count())->toBe(0);
});

test('a medication\'s schedule can be edited', function () {
    $user = User::factory()->create();
    $medication = Medication::factory()->for($user)->create(['name' => 'Propranolol']);
    MedicationSchedule::factory()->for($medication)->during(TimeOfDay::Night)->create();

    $this->actingAs($user)
        ->patch(route('medications.update', $medication), [
            'name' => 'Propranolol',
            'dose_amount' => 40,
            'dose_unit' => 'mg',
            'frequency' => 'twice_daily',
            'is_prescription' => true,
            'is_active' => true,
            'schedules' => [
                ['time_of_day' => null, 'time' => '08:00'],
                ['time_of_day' => 'bedtime', 'time' => null],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('medications'));

    $schedules = $medication->fresh()->schedules;

    expect($schedules)->toHaveCount(2)
        ->and($schedules[0]->formattedTime())->toBe('08:00')
        ->and($schedules[0]->position)->toBe(0)
        ->and($schedules[1]->time_of_day)->toBe(TimeOfDay::Bedtime)
        ->and($schedules[1]->position)->toBe(1)
        ->and(MedicationSchedule::count())->toBe(2);
});

test('a medication\'s schedule can be cleared', function () {
    $user = User::factory()->create();
    $medication = Medication::factory()->for($user)->create();
    MedicationSchedule::factory()->for($medication)->create();

    $this->actingAs($user)
        ->patch(route('medications.update', $medication), [
            'name' => 'Propranolol',
            'dose_amount' => 40,
            'dose_unit' => 'mg',
            'frequency' => 'ad_hoc',
            'is_prescription' => true,
            'is_active' => true,
            'schedules' => [],
        ])
        ->assertSessionHasNoErrors();

    expect($medication->fresh()->schedules)->toHaveCount(0);
});

test('an invalid schedule edit leaves the existing schedule untouched', function () {
    $user = User::factory()->create();
    $medication = Medication::factory()->for($user)->create();
    MedicationSchedule::factory()->for($medication)->during(TimeOfDay::Night)->create();

    $this->actingAs($user)
        ->patch(route('medications.update', $medication), [
            'name' => 'Propranolol',
            'dose_amount' => 40,
            'dose_unit' => 'mg',
            'frequency' => 'ad_hoc',
            'is_prescription' => true,
            'is_active' => true,
            'schedules' => [
                ['time_of_day' => null, 'time' => 'later'],
            ],
        ])
        ->assertSessionHasErrors(['schedules.0.time']);

    $schedules = $medication->fresh()->schedules;

    expect($schedules)->toHaveCount(1)
        ->and($schedules[0]->time_of_day)->toBe(TimeOfDay::Night);
});
