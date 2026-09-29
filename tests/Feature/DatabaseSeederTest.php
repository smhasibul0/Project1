<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Faker\Generator;
use Illuminate\Support\Facades\Hash;

test('seeding creates the first admin login without needing faker', function () {
    // A production install has no Faker (it is a dev dependency), so make any use of it fail.
    app()->singleton(Generator::class.':'.config('app.faker_locale'), function () {
        throw new RuntimeException('Faker is not installed in production.');
    });

    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'test@example.com')->firstOrFail();

    expect($admin->username)->toBe('testuser')
        ->and(Hash::check('password', $admin->password))->toBeTrue()
        ->and($admin->role->slug)->toBe('admin');
});

test('seeding again does not bring the default login back', function () {
    $this->seed(DatabaseSeeder::class);

    User::where('email', 'test@example.com')->update(['email' => 'owner@example.com', 'username' => 'owner']);

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1)
        ->and(User::where('email', 'test@example.com')->exists())->toBeFalse();
});
