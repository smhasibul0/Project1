<?php

use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Order;
use App\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Smalot\PdfParser\Parser;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = adminUser();
    CompanySetting::current()->update(['company_name' => 'Redwan Trading Corporation', 'currency_symbol' => '৳']);
});

/**
 * The text a downloaded PDF holds.
 */
function pdfText(TestResponse $response): string
{
    return (new Parser)->parseContent($response->getContent())->getText();
}

test('an order invoice downloads as a PDF', function () {
    $order = Order::factory()->create();
    $order->items()->create(['item_description' => 'Plastic toys', 'package_quantity' => 10, 'cbm' => 8, 'line_total' => 800]);

    $response = $this->actingAs($this->user)->get(route('order.invoice.pdf', $order->id));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="Invoice '.$order->order_no.'.pdf"');

    $text = pdfText($response);
    expect($text)->toContain('Redwan Trading Corporation')
        ->toContain('BILL')
        ->toContain($order->order_no)
        ->toContain('Plastic toys');
});

test('a quotation downloads as a PDF with the taka sign drawn, and nothing from the cost side', function () {
    $quotation = Quotation::factory()->create(['sell_rate_per_cbm' => 100, 'customer_charge' => 800, 'total_cbm' => 8]);
    $quotation->items()->create(['description' => 'Plastic toys', 'hs_code' => '9503.00.90', 'package_quantity' => 10, 'cbm' => 8, 'declared_value' => 20000]);

    $response = $this->actingAs($this->user)->get(route('quotation.pdf', $quotation->id));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="Quotation '.$quotation->quotation_no.'.pdf"');

    $text = pdfText($response);
    expect($text)->toContain('QUOTATION')
        ->toContain($quotation->quotation_no)
        ->toContain('৳')
        ->toContain('800.00')
        ->not->toContain('Profit')
        ->not->toContain('?'); // a character the fonts lack prints as "?"
});

test('a document whose customer was removed still prints', function () {
    $quotation = Quotation::factory()->create();
    Contact::whereKey($quotation->customer_id)->delete();

    $this->actingAs($this->user)->get(route('quotation.print', $quotation->id))->assertOk();
    $this->actingAs($this->user)->get(route('quotation.pdf', $quotation->id))->assertOk();
});

test('the printable pages offer the PDF download beside print', function () {
    $order = Order::factory()->create();
    $quotation = Quotation::factory()->create();
    $this->actingAs($this->user);

    $this->get(route('order.invoice', $order->id))->assertOk()
        ->assertSee(route('order.invoice.pdf', $order->id))->assertSee('Download PDF');
    $this->get(route('quotation.print', $quotation->id))->assertOk()
        ->assertSee(route('quotation.pdf', $quotation->id))->assertSee('Download PDF');

    $this->get(route('order.show', $order->id))->assertSee(route('order.invoice.pdf', $order->id));
    $this->get(route('quotation.show', $quotation->id))->assertSee(route('quotation.pdf', $quotation->id));
});

test('downloading a PDF needs the same permission as printing it', function () {
    $order = Order::factory()->create();
    $quotation = Quotation::factory()->create();
    $this->actingAs(userWithPermissions(['orders.view', 'quotations.view']));

    $this->get(route('order.invoice.pdf', $order->id))->assertForbidden();
    $this->get(route('quotation.pdf', $quotation->id))->assertForbidden();
});
