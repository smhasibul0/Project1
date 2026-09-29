<?php

use App\Models\User;

test('there is no public registration screen', function () {
    $this->get('/register')->assertNotFound();
});

test('nobody can sign themselves up', function () {
    $this->post('/register', [
        'first_name' => 'Test',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('the login screen does not offer a sign-up link', function () {
    $this->get('/login')
        ->assertOk()
        ->assertDontSee('Sign up');
});
