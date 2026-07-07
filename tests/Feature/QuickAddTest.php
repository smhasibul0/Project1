<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
});

test('category store returns JSON for an AJAX request (quick-add)', function () {
    $response = $this->actingAs($this->user)->postJson(route('category.store'), ['name' => 'Electronics']);

    $response->assertOk()->assertJsonStructure(['id', 'name'])->assertJson(['name' => 'Electronics']);
    expect(Category::where('name', 'Electronics')->exists())->toBeTrue();
});

test('brand store returns JSON for an AJAX request', function () {
    $response = $this->actingAs($this->user)->postJson(route('brand.store'), ['name' => 'Acme']);

    $response->assertOk()->assertJson(['name' => 'Acme']);
    expect(Brand::where('name', 'Acme')->exists())->toBeTrue();
});

test('unit store returns JSON for an AJAX request', function () {
    $response = $this->actingAs($this->user)->postJson(route('unit.store'), ['name' => 'Kilogram', 'short_name' => 'kg']);

    $response->assertOk()->assertJson(['name' => 'Kilogram']);
    expect(Unit::where('name', 'Kilogram')->exists())->toBeTrue();
});

test('a normal (non-AJAX) category store still redirects', function () {
    $this->actingAs($this->user)->post(route('category.store'), ['name' => 'Toys'])
        ->assertRedirect();
});

test('quick-add validation errors come back as JSON 422', function () {
    $this->actingAs($this->user)->postJson(route('category.store'), ['name' => ''])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});
