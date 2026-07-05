<?php

use App\Models\Contact;
use App\Models\CustomerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
});

test('a supplier can be created', function () {
    $response = $this->actingAs($this->user)->post(route('contact.store'), [
        'type' => 'supplier',
        'name' => 'John Supplier',
        'business_name' => 'Acme Imports',
        'mobile' => '01710000000',
        'opening_balance' => 500,
        'country' => 'China',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $contact = Contact::firstOrFail();
    expect($contact->type)->toBe('supplier');
    expect($contact->name)->toBe('John Supplier');
    expect($contact->opening_balance)->toEqual('500.00');
    expect($contact->added_by)->toBe($this->user->id);
    expect($contact->is_active)->toBeFalse(); // checkbox not sent
});

test('a customer can be created with a customer group', function () {
    $group = CustomerGroup::factory()->create();

    $this->actingAs($this->user)->post(route('contact.store'), [
        'type' => 'customer',
        'name' => 'Jane Customer',
        'customer_group_id' => $group->id,
        'credit_limit' => 10000,
        'is_active' => '1',
    ]);

    $contact = Contact::firstOrFail();
    expect($contact->type)->toBe('customer');
    expect($contact->customer_group_id)->toBe($group->id);
    expect($contact->credit_limit)->toEqual('10000.00');
    expect($contact->is_active)->toBeTrue();
});

test('a customer gets an auto-generated CO code and stores lead_by', function () {
    $this->actingAs($this->user)->post(route('contact.store'), [
        'type' => 'customer',
        'name' => 'Coded Customer',
        'lead_by' => 'Trade Show Referral',
    ]);

    $contact = Contact::firstOrFail();
    expect($contact->contact_code)->toBe('CO0001');
    expect($contact->lead_by)->toBe('Trade Show Referral');
});

test('a supplier gets an auto-generated CU code', function () {
    $this->actingAs($this->user)->post(route('contact.store'), [
        'type' => 'supplier',
        'name' => 'Coded Supplier',
    ]);

    expect(Contact::firstOrFail()->contact_code)->toBe('CU0001');
});

test('a supplier stores import fields and falls back to business name for the contact name', function () {
    $this->actingAs($this->user)->post(route('contact.store'), [
        'type' => 'supplier',
        'business_name' => 'Guangzhou Trading Co',
        'bank_details' => 'Bank of China, A/C 123456',
        'warehouse_address' => 'Shed 4, Port Area',
        'shipping_mark' => 'GZ-EXP-2026',
        'more_information' => 'Preferred supplier',
        'country' => 'China',
    ]);

    $contact = Contact::firstOrFail();
    expect($contact->contact_code)->toBe('CU0001');
    expect($contact->business_name)->toBe('Guangzhou Trading Co');
    expect($contact->name)->toBe('Guangzhou Trading Co'); // fell back from business name
    expect($contact->bank_details)->toBe('Bank of China, A/C 123456');
    expect($contact->warehouse_address)->toBe('Shed 4, Port Area');
    expect($contact->shipping_mark)->toBe('GZ-EXP-2026');
    expect($contact->more_information)->toBe('Preferred supplier');
});

test('customer codes increment sequentially', function () {
    Contact::factory()->customer()->create();
    Contact::factory()->customer()->create();

    expect(Contact::customers()->orderBy('id')->pluck('contact_code')->all())
        ->toBe(['CO0001', 'CO0002']);
});

test('a customer stores pay term, credit limit and defaults advance balance to zero', function () {
    $this->actingAs($this->user)->post(route('contact.store'), [
        'type' => 'customer',
        'name' => 'Termed Customer',
        'credit_limit' => 5000,
        'pay_term_number' => 30,
        'pay_term_type' => 'days',
    ]);

    $contact = Contact::firstOrFail();
    expect($contact->pay_term_number)->toBe(30);
    expect($contact->pay_term_type)->toBe('days');
    expect($contact->credit_limit)->toEqual('5000.00');
    expect($contact->advance_balance)->toEqual('0.00');
});

test('creating a contact requires a name', function () {
    $response = $this->actingAs($this->user)->post(route('contact.store'), [
        'type' => 'supplier',
    ]);

    $response->assertSessionHasErrors('name');
    expect(Contact::count())->toBe(0);
});

test('the suppliers scope returns suppliers and both, but not customers', function () {
    Contact::factory()->supplier()->create();
    Contact::factory()->customer()->create();
    Contact::factory()->create(['type' => 'both']);

    expect(Contact::suppliers()->count())->toBe(2);
    expect(Contact::customers()->count())->toBe(2);
});

test('the suppliers and customers pages load', function () {
    Contact::factory()->supplier()->create(['business_name' => 'Supplier Co']);
    Contact::factory()->customer()->create(['business_name' => 'Customer Co']);

    $this->actingAs($this->user)->get(route('suppliers.index'))
        ->assertOk()
        ->assertSee('Supplier Co')
        ->assertDontSee('Customer Co');

    $this->actingAs($this->user)->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Customer Co')
        ->assertDontSee('Supplier Co');
});

test('a contact can be updated', function () {
    $contact = Contact::factory()->supplier()->create(['name' => 'Old Name']);

    $this->actingAs($this->user)->put(route('contact.update', $contact->id), [
        'type' => 'supplier',
        'name' => 'New Name',
        'mobile' => '01999999999',
    ]);

    $contact->refresh();
    expect($contact->name)->toBe('New Name');
    expect($contact->mobile)->toBe('01999999999');
});

test('a contact can be deleted', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->user)->delete(route('contact.delete', $contact->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Contact::count())->toBe(0);
});

test('a contact can be deactivated and reactivated', function () {
    $contact = Contact::factory()->create(['is_active' => true]);

    $this->actingAs($this->user)->patch(route('contact.toggle', $contact->id))
        ->assertRedirect()
        ->assertSessionHas('success');
    expect($contact->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->user)->patch(route('contact.toggle', $contact->id));
    expect($contact->fresh()->is_active)->toBeTrue();
});

test('a customer group can be created, updated and deleted', function () {
    $this->actingAs($this->user)->post(route('customer.group.store'), [
        'name' => 'Wholesale',
        'discount_type' => 'percentage',
        'discount_amount' => 10,
    ]);

    $group = CustomerGroup::firstOrFail();
    expect($group->name)->toBe('Wholesale');
    expect($group->discount_amount)->toEqual('10.00');

    $this->actingAs($this->user)->put(route('customer.group.update', $group->id), [
        'name' => 'Retail',
        'discount_type' => 'fixed',
        'discount_amount' => 50,
    ]);

    $group->refresh();
    expect($group->name)->toBe('Retail');
    expect($group->discount_type)->toBe('fixed');

    $this->actingAs($this->user)->delete(route('customer.group.delete', $group->id));
    expect(CustomerGroup::count())->toBe(0);
});

test('the customer groups page loads', function () {
    CustomerGroup::factory()->create(['name' => 'VIP Tier']);

    $this->actingAs($this->user)->get(route('customer.groups'))
        ->assertOk()
        ->assertSee('VIP Tier');
});

test('deleting a customer group leaves its customers intact', function () {
    $group = CustomerGroup::factory()->create();
    $contact = Contact::factory()->customer()->create(['customer_group_id' => $group->id]);

    $this->actingAs($this->user)->delete(route('customer.group.delete', $group->id));

    $contact->refresh();
    expect($contact->exists)->toBeTrue();
    expect($contact->customer_group_id)->toBeNull();
});
