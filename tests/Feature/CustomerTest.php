<?php

use App\Models\Contact;
use App\Models\CustomerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
});

test('a customer can be created with a customer group', function () {
    $group = CustomerGroup::factory()->create();

    $response = $this->actingAs($this->user)->post(route('contact.store'), [
        'name' => 'Jane Customer',
        'customer_group_id' => $group->id,
        'credit_limit' => 10000,
        'opening_balance' => 500,
        'is_active' => '1',
    ]);

    $response->assertRedirect()->assertSessionHas('success');

    $contact = Contact::firstOrFail();
    expect($contact->name)->toBe('Jane Customer');
    expect($contact->customer_group_id)->toBe($group->id);
    expect($contact->credit_limit)->toEqual('10000.00');
    expect($contact->opening_balance)->toEqual('500.00');
    expect($contact->added_by)->toBe($this->user->id);
    expect($contact->is_active)->toBeTrue();
});

test('a customer without the active checkbox is stored inactive', function () {
    $this->actingAs($this->user)->post(route('contact.store'), ['name' => 'Dormant Customer']);

    expect(Contact::firstOrFail()->is_active)->toBeFalse();
});

test('a customer gets an auto-generated CO code and stores lead_by', function () {
    $this->actingAs($this->user)->post(route('contact.store'), [
        'name' => 'Coded Customer',
        'lead_by' => 'Trade Show Referral',
    ]);

    $contact = Contact::firstOrFail();
    expect($contact->contact_code)->toBe('CO0001');
    expect($contact->lead_by)->toBe('Trade Show Referral');
});

test('a new customer gets a shipping mark from their name', function () {
    $this->actingAs($this->user)->post(route('contact.store'), ['name' => 'Momin Traders']);

    expect(Contact::firstOrFail()->shipping_mark)->toBe('RTC/MOM');
});

test('shipping marks stay unique when names collide', function () {
    Contact::factory()->create(['name' => 'Momin Traders', 'shipping_mark' => 'RTC/MOM']);

    // Three letters taken, so it widens to four.
    expect(Contact::suggestShippingMark('Mominul Haque'))->toBe('RTC/MOMI');

    // Both taken, so it counts up from the four-letter form.
    Contact::factory()->create(['name' => 'Momtaz Ltd', 'shipping_mark' => 'RTC/MOMT']);
    expect(Contact::suggestShippingMark('Momtahina Enterprise'))->toBe('RTC/MOMT2');

    $this->actingAs($this->user)->post(route('contact.store'), ['name' => 'Momtahin Corp']);
    expect(Contact::where('name', 'Momtahin Corp')->value('shipping_mark'))->toBe('RTC/MOMT2');

    // And again, so two customers never share a mark.
    $this->actingAs($this->user)->post(route('contact.store'), ['name' => 'Momtahir Sons']);
    expect(Contact::where('name', 'Momtahir Sons')->value('shipping_mark'))->toBe('RTC/MOMT3');
    expect(Contact::pluck('shipping_mark')->duplicates())->toBeEmpty();
});

test('a shipping mark typed in by hand is kept, and cannot duplicate another customer', function () {
    $this->actingAs($this->user)->post(route('contact.store'), [
        'name' => 'Karim Brothers',
        'shipping_mark' => 'RTC/KB-01',
    ]);
    expect(Contact::firstOrFail()->shipping_mark)->toBe('RTC/KB-01');

    $this->actingAs($this->user)->post(route('contact.store'), [
        'name' => 'Kabir Steel',
        'shipping_mark' => 'RTC/KB-01',
    ])->assertSessionHasErrors('shipping_mark');

    expect(Contact::count())->toBe(1);
});

test('a customer keeps their shipping mark when their name changes', function () {
    $contact = Contact::factory()->create(['name' => 'Momin Traders', 'shipping_mark' => 'RTC/MOM']);

    $this->actingAs($this->user)->put(route('contact.update', $contact->id), [
        'name' => 'Zahir Traders',
        'shipping_mark' => 'RTC/MOM',
    ])->assertSessionHasNoErrors();

    expect($contact->fresh()->shipping_mark)->toBe('RTC/MOM');
});

test('the shipping mark suggestion endpoint answers as a customer is typed in', function () {
    Contact::factory()->create(['shipping_mark' => 'RTC/MOM']);

    $this->actingAs($this->user)
        ->getJson(route('customer.shipping.mark', ['name' => 'Momin Traders']))
        ->assertOk()
        ->assertJson(['shipping_mark' => 'RTC/MOMI']);

    // A name with no letters in it still gets something usable.
    $this->actingAs($this->user)
        ->getJson(route('customer.shipping.mark', ['name' => '12345']))
        ->assertJson(['shipping_mark' => 'RTC/CUS']);
});

test('customer codes increment sequentially', function () {
    Contact::factory()->customer()->create();
    Contact::factory()->customer()->create();

    expect(Contact::customers()->orderBy('id')->pluck('contact_code')->all())
        ->toBe(['CO0001', 'CO0002']);
});

test('the contact name falls back to the business name', function () {
    $this->actingAs($this->user)->post(route('contact.store'), [
        'business_name' => 'Guangzhou Trading Co',
        'more_information' => 'Ships every month',
        'country' => 'China',
    ]);

    $contact = Contact::firstOrFail();
    expect($contact->business_name)->toBe('Guangzhou Trading Co');
    expect($contact->name)->toBe('Guangzhou Trading Co');
    expect($contact->more_information)->toBe('Ships every month');
});

test('a customer stores pay term, credit limit and defaults advance balance to zero', function () {
    $this->actingAs($this->user)->post(route('contact.store'), [
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

test('creating a customer requires a name or a business name', function () {
    $this->actingAs($this->user)->post(route('contact.store'), [])
        ->assertSessionHasErrors('name');

    expect(Contact::count())->toBe(0);
});

test('the customers page loads', function () {
    Contact::factory()->customer()->create(['business_name' => 'Customer Co', 'name' => 'Customer Co']);

    $this->actingAs($this->user)->get(route('customers.index'))
        ->assertOk()
        ->assertSee('Customer Co');
});

test('a customer can be updated', function () {
    $contact = Contact::factory()->customer()->create(['name' => 'Old Name']);

    $this->actingAs($this->user)->put(route('contact.update', $contact->id), [
        'name' => 'New Name',
        'mobile' => '01999999999',
    ]);

    $contact->refresh();
    expect($contact->name)->toBe('New Name');
    expect($contact->mobile)->toBe('01999999999');
});

test('a customer can be deleted', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->user)->delete(route('contact.delete', $contact->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Contact::count())->toBe(0);
});

test('a customer can be deactivated and reactivated', function () {
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
