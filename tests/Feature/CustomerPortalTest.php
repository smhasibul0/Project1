<?php

use App\Models\Contact;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A customer portal login: a User with the customer role linked to a Contact.
 */
function customerUser(Contact $contact): User
{
    $role = Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer', 'is_system' => true]);

    return User::factory()->create(['role_id' => $role->id, 'contact_id' => $contact->id]);
}

test('admin can create a customer portal login linked to the contact', function () {
    Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer', 'is_system' => true]);
    $contact = Contact::factory()->customer()->create();

    $this->actingAs(adminUser())->post(route('contact.create.login', $contact->id), [
        'username' => 'customer1',
        'email' => 'login@example.com',
        'password' => 'secret123',
    ])->assertRedirect()->assertSessionHas('success');

    $user = User::where('email', 'login@example.com')->firstOrFail();
    expect($user->username)->toBe('customer1');
    expect($user->contact_id)->toBe($contact->id);
    expect($user->isCustomer())->toBeTrue();
});

test('a customer login can be created from the Users page with a linked contact', function () {
    $role = Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer', 'is_system' => true]);
    $contact = Contact::factory()->customer()->create();

    $this->actingAs(adminUser())->post(route('user.store'), [
        'first_name' => 'Sakib', 'username' => 'sakibx', 'email' => 'sakibx@example.com',
        'role_id' => $role->id, 'contact_id' => $contact->id, 'password' => 'password12', 'is_active' => 1,
    ])->assertRedirect()->assertSessionHas('success');

    expect(User::where('email', 'sakibx@example.com')->first()->contact_id)->toBe($contact->id);
});

test('a customer login created without a contact is rejected', function () {
    $role = Role::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer', 'is_system' => true]);

    $this->actingAs(adminUser())->post(route('user.store'), [
        'first_name' => 'X', 'username' => 'xuser', 'email' => 'xuser@example.com',
        'role_id' => $role->id, 'password' => 'password12',
    ])->assertSessionHasErrors('contact_id');

    expect(User::where('email', 'xuser@example.com')->exists())->toBeFalse();
});

test('a customer cannot be given a second login', function () {
    $contact = Contact::factory()->customer()->create();
    customerUser($contact);

    $this->actingAs(adminUser())->post(route('contact.create.login', $contact->id), [
        'email' => 'another@example.com',
        'password' => 'secret123',
    ])->assertSessionHas('error');

    expect(User::where('contact_id', $contact->id)->count())->toBe(1);
});

test('login redirects a customer to the portal', function () {
    $contact = Contact::factory()->customer()->create();
    $user = customerUser($contact); // factory password is "password"

    $this->post(route('login'), ['login' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('portal.dashboard'));
});

test('a customer can reach the portal and is bounced from the admin panel', function () {
    $user = customerUser(Contact::factory()->customer()->create());

    $this->actingAs($user)->get(route('portal.dashboard'))->assertOk();
    // Hitting an admin URL redirects a customer to their portal (not a 403).
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('portal.dashboard'));
});

test('a non-customer cannot reach the portal', function () {
    $this->actingAs(adminUser())->get(route('portal.dashboard'))->assertForbidden();
});

test('a customer can submit a quotation request scoped to their contact', function () {
    $contact = Contact::factory()->customer()->create();
    $user = customerUser($contact);

    $this->actingAs($user)->post(route('portal.quotation.store'), [
        'items' => [['description' => 'LED Bulbs 9W', 'package_quantity' => 100]],
    ])->assertRedirect(route('portal.quotations'));

    $q = Quotation::firstOrFail();
    expect($q->customer_id)->toBe($contact->id);
    expect($q->status)->toBe('submitted');
    expect($q->items)->toHaveCount(1);
});

test('a customer cannot view another customer order or quotation', function () {
    $user = customerUser(Contact::factory()->customer()->create());
    $other = Contact::factory()->customer()->create();
    $otherOrder = Order::factory()->create(['customer_id' => $other->id]);
    $otherQuote = Quotation::factory()->create(['customer_id' => $other->id]);

    $this->actingAs($user)->get(route('portal.order.show', $otherOrder->id))->assertNotFound();
    $this->actingAs($user)->get(route('portal.quotation.show', $otherQuote->id))->assertNotFound();
});

test('a customer can accept a priced quotation but not an unpriced one', function () {
    $contact = Contact::factory()->customer()->create();
    $user = customerUser($contact);

    $priced = Quotation::factory()->create(['customer_id' => $contact->id, 'status' => 'submitted', 'grand_total' => 5000]);
    $this->actingAs($user)->post(route('portal.quotation.accept', $priced->id))->assertRedirect();
    expect($priced->fresh()->status)->toBe('accepted');

    $unpriced = Quotation::factory()->create(['customer_id' => $contact->id, 'status' => 'submitted', 'grand_total' => 0]);
    $this->actingAs($user)->post(route('portal.quotation.accept', $unpriced->id))->assertSessionHas('error');
    expect($unpriced->fresh()->status)->toBe('submitted');
});

test('portal pages load', function () {
    $contact = Contact::factory()->customer()->create();
    $user = customerUser($contact);
    $order = Order::factory()->create(['customer_id' => $contact->id]);
    $order->items()->create(['item_description' => 'Toys', 'quantity' => 2, 'our_asking_price' => 100, 'supplier_asking_price' => 60, 'line_total' => 200]);
    $quotation = Quotation::factory()->create(['customer_id' => $contact->id]);

    foreach (['portal.dashboard', 'portal.quotations', 'portal.quotation.create', 'portal.orders', 'portal.payments'] as $route) {
        $this->actingAs($user)->get(route($route))->assertOk();
    }

    $this->actingAs($user)->get(route('portal.order.show', $order->id))->assertOk();
    $this->actingAs($user)->get(route('portal.quotation.show', $quotation->id))->assertOk();
});
