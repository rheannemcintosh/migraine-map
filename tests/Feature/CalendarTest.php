<?php

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
