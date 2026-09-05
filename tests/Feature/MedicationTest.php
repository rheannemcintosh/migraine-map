<?php

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Models\Medication;
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
                ->where('frequency', 'Ad hoc (as needed)')
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
