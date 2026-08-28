<?php

use App\Models\Contact;
use App\Models\HsCode;
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

test('a customer can submit a request with a tariff line and a free-text item', function () {
    $contact = Contact::factory()->customer()->create();
    $user = customerUser($contact);
    $hsCode = HsCode::factory()->create(['code' => '3911.90.00', 'description' => 'Polysulphides, in primary forms']);

    $this->actingAs($user)->post(route('portal.quotation.store'), [
        'items' => [
            ['hs_code_id' => $hsCode->id, 'hs_code' => $hsCode->code, 'description' => $hsCode->description, 'package_quantity' => 100, 'cbm' => 4, 'declared_value' => 5000],
            ['description' => 'Custom widget', 'package_quantity' => 10, 'cbm' => 1],
        ],
    ])->assertRedirect(route('portal.quotations'));

    $q = Quotation::with('items')->firstOrFail();
    expect($q->customer_id)->toBe($contact->id);
    expect($q->status)->toBe('requested');
    expect($q->source)->toBe('customer');
    expect($q->items)->toHaveCount(2);

    $tariffLine = $q->items->firstWhere('hs_code_id', $hsCode->id);
    expect($tariffLine)->not->toBeNull();
    expect($tariffLine->hs_code)->toBe('3911.90.00');
    expect($tariffLine->declared_value)->toEqual('5000.00');
});

test('portal requests appear on the admin quotation requests page (admin quotes do not)', function () {
    $contact = Contact::factory()->customer()->create(['name' => 'Portal Cust']);
    $this->actingAs(customerUser($contact))->post(route('portal.quotation.store'), [
        'items' => [['description' => 'Thing', 'package_quantity' => 1]],
    ]);
    Quotation::factory()->create(['source' => 'admin']);

    $this->actingAs(adminUser())->get(route('quotation.requests'))
        ->assertOk()
        ->assertSee('Quotation Requests')
        ->assertSee('Portal Cust');

    expect(Quotation::where('source', 'customer')->count())->toBe(1);
});

test('a customer cannot view another customer order or quotation', function () {
    $user = customerUser(Contact::factory()->customer()->create());
    $other = Contact::factory()->customer()->create();
    $otherOrder = Order::factory()->create(['customer_id' => $other->id]);
    $otherQuote = Quotation::factory()->create(['customer_id' => $other->id]);

    $this->actingAs($user)->get(route('portal.order.show', $otherOrder->id))->assertNotFound();
    $this->actingAs($user)->get(route('portal.quotation.show', $otherQuote->id))->assertNotFound();
});

test('a customer can accept or negotiate a quoted quotation, but not one still requested', function () {
    $contact = Contact::factory()->customer()->create();
    $user = customerUser($contact);

    // A quoted quotation can be accepted.
    $quoted = Quotation::factory()->create(['customer_id' => $contact->id, 'status' => 'quoted', 'grand_total' => 5000]);
    $this->actingAs($user)->post(route('portal.quotation.accept', $quoted->id))->assertRedirect();
    expect($quoted->fresh()->status)->toBe('accepted');

    // Another quoted one can be negotiated.
    $quoted2 = Quotation::factory()->create(['customer_id' => $contact->id, 'status' => 'quoted', 'grand_total' => 5000]);
    $this->actingAs($user)->post(route('portal.quotation.negotiate', $quoted2->id))->assertRedirect();
    expect($quoted2->fresh()->status)->toBe('negotiating');

    // A request that hasn't been quoted yet cannot be actioned.
    $requested = Quotation::factory()->create(['customer_id' => $contact->id, 'status' => 'requested']);
    $this->actingAs($user)->post(route('portal.quotation.accept', $requested->id))->assertSessionHas('error');
    expect($requested->fresh()->status)->toBe('requested');
});

test('portal pages load', function () {
    $contact = Contact::factory()->customer()->create();
    $user = customerUser($contact);
    $order = Order::factory()->create(['customer_id' => $contact->id]);
    $order->items()->create(['item_description' => 'Toys', 'quantity' => 2, 'cbm' => 2, 'declared_value' => 120, 'line_total' => 200]);
    $quotation = Quotation::factory()->create(['customer_id' => $contact->id]);

    foreach (['portal.dashboard', 'portal.quotations', 'portal.quotation.create', 'portal.orders', 'portal.payments'] as $route) {
        $this->actingAs($user)->get(route($route))->assertOk();
    }

    $this->actingAs($user)->get(route('portal.order.show', $order->id))->assertOk();
    $this->actingAs($user)->get(route('portal.quotation.show', $quotation->id))->assertOk();
});
