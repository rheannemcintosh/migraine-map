<?php

use App\Enums\DoseUnit;
use App\Enums\MedicationFrequency;
use App\Enums\TimeOfDay;
use App\Models\Medication;
use App\Models\MedicationSchedule;
use App\Models\MedicationScheduleConfirmation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A twice-daily medication scheduled since the start of the year.
 *
 * @return array{Medication, MedicationSchedule, MedicationSchedule}
 */
function scheduledMedication(User $user, string $since = '2026-01-01'): array
{
    $medication = Medication::factory()->for($user)->create([
        'name' => 'Propranolol',
        'dose_amount' => 40,
        'dose_unit' => DoseUnit::Milligram,
        'frequency' => MedicationFrequency::TwiceDaily,
        'created_at' => $since,
    ]);

    $waking = MedicationSchedule::factory()->for($medication)->during(TimeOfDay::Waking)->create(['position' => 0, 'created_at' => $since]);
    $bedtime = MedicationSchedule::factory()->for($medication)->during(TimeOfDay::Bedtime)->create(['position' => 1, 'created_at' => $since]);

    return [$medication, $waking, $bedtime];
}

test('the calendar lists each scheduled dose due on a day with its confirmation state', function () {
    Carbon::setTestNow('2026-01-03 12:00:00');
    $user = User::factory()->create();
    [, $waking, $bedtime] = scheduledMedication($user);

    $confirmation = MedicationScheduleConfirmation::factory()->for($user)->for($waking, 'schedule')->create(['date' => '2026-01-02']);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Calendar')
            ->has('scheduledDosesByDay', 3)
            ->has('scheduledDosesByDay.2026-01-02', 2)
            ->where('scheduledDosesByDay.2026-01-02.0.scheduleId', $waking->id)
            ->where('scheduledDosesByDay.2026-01-02.0.name', 'Propranolol')
            ->where('scheduledDosesByDay.2026-01-02.0.dose', '40 mg')
            ->where('scheduledDosesByDay.2026-01-02.0.label', 'Upon waking')
            ->where('scheduledDosesByDay.2026-01-02.0.confirmationId', $confirmation->id)
            ->where('scheduledDosesByDay.2026-01-02.1.scheduleId', $bedtime->id)
            ->where('scheduledDosesByDay.2026-01-02.1.label', 'Before sleeping')
            ->where('scheduledDosesByDay.2026-01-02.1.confirmationId', null)
            ->has('scheduledDosesByDay.2026-01-03', 2)
        );
});

test('a day with an unconfirmed scheduled dose is flagged as missing medication', function () {
    Carbon::setTestNow('2026-01-03 12:00:00');
    $user = User::factory()->create();
    [, $waking, $bedtime] = scheduledMedication($user);

    MedicationScheduleConfirmation::factory()->for($user)->for($waking, 'schedule')->create(['date' => '2026-01-02']);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('missedMedicationDays', ['2026-01-01', '2026-01-02', '2026-01-03'])
        );

    MedicationScheduleConfirmation::factory()->for($user)->for($bedtime, 'schedule')->create(['date' => '2026-01-02']);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('missedMedicationDays', ['2026-01-01', '2026-01-03'])
        );
});

test('days before a dose was scheduled, future days and inactive medications are never flagged', function () {
    Carbon::setTestNow('2026-03-10 12:00:00');
    $user = User::factory()->create();
    scheduledMedication($user, since: '2026-03-09 15:00:00');

    $inactive = Medication::factory()->for($user)->create(['is_active' => false, 'created_at' => '2026-01-01']);
    MedicationSchedule::factory()->for($inactive)->create(['created_at' => '2026-01-01']);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('missedMedicationDays', ['2026-03-09', '2026-03-10'])
            ->has('scheduledDosesByDay', 2)
            ->has('scheduledDosesByDay.2026-03-09', 2)
        );
});

test('a user without scheduled medications has no flagged days', function () {
    Carbon::setTestNow('2026-03-10');
    $user = User::factory()->create();
    Medication::factory()->for($user)->create(['frequency' => MedicationFrequency::AdHoc]);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('missedMedicationDays', [])
            ->where('scheduledDosesByDay', [])
        );
});

test('another user\'s schedules do not appear on the calendar', function () {
    Carbon::setTestNow('2026-01-02');
    $user = User::factory()->create();
    scheduledMedication(User::factory()->create());

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page->where('missedMedicationDays', []));
});

test('a scheduled dose can be confirmed for a day', function () {
    Carbon::setTestNow('2026-01-03');
    $user = User::factory()->create();
    [, $waking] = scheduledMedication($user);

    $this->actingAs($user)
        ->post(route('medication-confirmations.store'), [
            'medication_schedule_id' => $waking->id,
            'date' => '2026-01-02',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('calendar', ['year' => 2026]));

    $confirmation = $user->medicationScheduleConfirmations()->sole();

    expect($confirmation->medication_schedule_id)->toBe($waking->id)
        ->and($confirmation->date->toDateString())->toBe('2026-01-02');
});

test('each dose of a medication taken more than once a day is confirmed independently', function () {
    Carbon::setTestNow('2026-01-03');
    $user = User::factory()->create();
    [, $waking, $bedtime] = scheduledMedication($user);

    $this->actingAs($user)
        ->post(route('medication-confirmations.store'), ['medication_schedule_id' => $waking->id, 'date' => '2026-01-02'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page
            ->whereNot('scheduledDosesByDay.2026-01-02.0.confirmationId', null)
            ->where('scheduledDosesByDay.2026-01-02.1.confirmationId', null)
        );

    $this->actingAs($user)
        ->post(route('medication-confirmations.store'), ['medication_schedule_id' => $bedtime->id, 'date' => '2026-01-02'])
        ->assertSessionHasNoErrors();

    expect($user->medicationScheduleConfirmations()->count())->toBe(2);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page
            ->whereNot('scheduledDosesByDay.2026-01-02.1.confirmationId', null)
            ->where('missedMedicationDays', ['2026-01-01', '2026-01-03'])
        );
});

test('a dose cannot be confirmed twice for the same day', function () {
    Carbon::setTestNow('2026-01-03');
    $user = User::factory()->create();
    [, $waking] = scheduledMedication($user);
    MedicationScheduleConfirmation::factory()->for($user)->for($waking, 'schedule')->create(['date' => '2026-01-02']);

    $this->actingAs($user)
        ->post(route('medication-confirmations.store'), ['medication_schedule_id' => $waking->id, 'date' => '2026-01-02'])
        ->assertSessionHasErrors('medication_schedule_id');

    expect($user->medicationScheduleConfirmations()->count())->toBe(1);
});

test('a dose cannot be confirmed for a future day', function () {
    Carbon::setTestNow('2026-01-03');
    $user = User::factory()->create();
    [, $waking] = scheduledMedication($user);

    $this->actingAs($user)
        ->post(route('medication-confirmations.store'), ['medication_schedule_id' => $waking->id, 'date' => '2026-01-04'])
        ->assertSessionHasErrors('date');

    expect($user->medicationScheduleConfirmations()->count())->toBe(0);
});

test('only the user\'s own scheduled doses can be confirmed', function () {
    Carbon::setTestNow('2026-01-03');
    $user = User::factory()->create();
    [, $waking] = scheduledMedication(User::factory()->create());

    $this->actingAs($user)
        ->post(route('medication-confirmations.store'), ['medication_schedule_id' => $waking->id, 'date' => '2026-01-02'])
        ->assertSessionHasErrors('medication_schedule_id');

    expect(MedicationScheduleConfirmation::count())->toBe(0);
});

test('a dose confirmation can be undone', function () {
    Carbon::setTestNow('2026-01-03');
    $user = User::factory()->create();
    [, $waking] = scheduledMedication($user);
    $confirmation = MedicationScheduleConfirmation::factory()->for($user)->for($waking, 'schedule')->create(['date' => '2026-01-02']);

    $this->actingAs($user)
        ->delete(route('medication-confirmations.destroy', $confirmation))
        ->assertRedirect(route('calendar', ['year' => 2026]));

    $this->assertModelMissing($confirmation);

    $this->actingAs($user)
        ->get(route('calendar'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduledDosesByDay.2026-01-02.0.confirmationId', null)
            ->where('missedMedicationDays', ['2026-01-01', '2026-01-02', '2026-01-03'])
        );
});

test('a user cannot undo another user\'s confirmation', function () {
    $user = User::factory()->create();
    $confirmation = MedicationScheduleConfirmation::factory()->create(['date' => '2026-01-02']);

    $this->actingAs($user)
        ->delete(route('medication-confirmations.destroy', $confirmation))
        ->assertForbidden();

    $this->assertModelExists($confirmation);
});

test('editing a medication keeps the confirmations of unchanged doses', function () {
    Carbon::setTestNow('2026-01-03');
    $user = User::factory()->create();
    [$medication, $waking, $bedtime] = scheduledMedication($user);
    $kept = MedicationScheduleConfirmation::factory()->for($user)->for($waking, 'schedule')->create(['date' => '2026-01-02']);
    $dropped = MedicationScheduleConfirmation::factory()->for($user)->for($bedtime, 'schedule')->create(['date' => '2026-01-02']);

    $this->actingAs($user)
        ->patch(route('medications.update', $medication), [
            'name' => 'Propranolol',
            'dose_amount' => 40,
            'dose_unit' => 'mg',
            'frequency' => 'twice_daily',
            'is_prescription' => true,
            'is_active' => true,
            'schedules' => [
                ['time_of_day' => null, 'time' => '14:00'],
                ['time_of_day' => 'waking', 'time' => null],
            ],
        ])
        ->assertSessionHasNoErrors();

    $schedules = $medication->refresh()->schedules;

    expect($schedules)->toHaveCount(2)
        ->and($schedules[0]->formattedTime())->toBe('14:00')
        ->and($schedules[1]->id)->toBe($waking->id)
        ->and($schedules[1]->position)->toBe(1);

    $this->assertModelExists($kept);
    $this->assertModelMissing($dropped);
    $this->assertModelMissing($bedtime);
});
