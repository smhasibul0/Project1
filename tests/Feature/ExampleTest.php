<?php

use App\Models\Role;
use App\Models\User;

test('opening the site as a guest leads to the login page', function () {
    $this->get('/')->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('opening the site signed in leads to the dashboard', function () {
    $this->actingAs(adminUser())->get('/')->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk();
});

test('opening the site as a customer leads to their portal', function () {
    $customerRole = Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer', 'is_system' => true]);

    $this->actingAs(User::factory()->create(['role_id' => $customerRole->id]))
        ->get('/')
        ->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('portal.dashboard'));
});
