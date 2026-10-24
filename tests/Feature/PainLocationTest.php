<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('pain-location'))->assertRedirect(route('login'));
});

test('the pain location tab renders for authenticated users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('pain-location'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('PainLocation'));
});
