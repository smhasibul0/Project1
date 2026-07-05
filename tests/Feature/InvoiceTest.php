<?php

use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\NumberToWords;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
});

test('number to words renders amounts correctly', function () {
    expect(NumberToWords::make(44000))->toBe('Forty Four Thousand Taka Only');
    expect(NumberToWords::make(0))->toBe('Zero Taka Only');
    expect(NumberToWords::make(105))->toBe('One Hundred Five Taka Only');
    expect(NumberToWords::make(2500.75))->toBe('Two Thousand Five Hundred Taka Only');
});

test('the invoice page renders order data, company info and amount in words', function () {
    CompanySetting::create(['company_name' => 'Redwan Trading Corporation', 'currency' => 'BDT']);
    $customer = Contact::factory()->customer()->create(['contact_code' => 'CID-014', 'name' => 'Momin']);

    $this->actingAs($this->user)->post(route('order.store'), [
        'customer_id' => $customer->id,
        'shipment_no' => 'RTC 2345/20',
        'shipping_mark' => 'RTC/REZA/SHA',
        'items' => [
            ['item_description' => 'Toy', 'package_quantity' => 10, 'quantity' => 220, 'our_asking_price' => 200, 'supplier_asking_price' => 100],
        ],
    ]);
    $order = Order::firstOrFail();

    $this->actingAs($this->user)->get(route('order.invoice', $order->id))
        ->assertOk()
        ->assertSee('Redwan Trading Corporation')
        ->assertSee($order->order_no)
        ->assertSee('CID-014')
        ->assertSee('RTC 2345/20')
        ->assertSee('Forty Four Thousand Taka Only');
});

test('admin can update company settings', function () {
    $this->actingAs($this->user)->post(route('settings.company.update'), [
        'company_name' => 'Acme Imports',
        'default_terms' => 'Net 15 Days',
        'primary_color' => '#ff0000',
    ])->assertSessionHas('success');

    expect(CompanySetting::current()->company_name)->toBe('Acme Imports');
    expect(CompanySetting::current()->default_terms)->toBe('Net 15 Days');
});

test('company settings require the settings.manage permission', function () {
    $role = Role::create(['name' => 'Ops', 'slug' => 'ops']);
    $ops = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($ops)->get(route('settings.company'))->assertForbidden();
    $this->actingAs($ops)->post(route('settings.company.update'), ['company_name' => 'X'])->assertForbidden();
});
