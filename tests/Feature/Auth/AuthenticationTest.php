<?php

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can authenticate using their username', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'login' => $user->username,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('the login page carries the logo uploaded in company settings', function () {
    $this->get('/login')->assertSee('ri-ship-2-line', false);

    $this->actingAs(adminUser())->post(route('settings.company.update'), [
        'company_name' => 'Redwan Trading Corporation',
        'logo' => UploadedFile::fake()->image('logo.png', 600, 200),
    ])->assertSessionHas('success');
    auth()->logout();

    $logo = asset('upload/company/'.CompanySetting::current()->logo);

    try {
        // The brand panel and the sign-in card both show it; the ship icon is gone.
        $response = $this->get('/login')->assertOk()->assertDontSee('ri-ship-2-line', false);
        expect(substr_count($response->getContent(), 'src="'.$logo.'"'))->toBe(2);
    } finally {
        File::delete(public_path('upload/company/'.CompanySetting::current()->logo));
    }
});
