<?php

namespace Tests\Feature;

use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterInvoiceCreateEditTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePart(string $code, string $name, float $mrp, int $stock): SparePart
    {
        return SparePart::create([
            'part_name' => $name,
            'part_code' => $code,
            'category' => 'battery',
            'unit' => 'pcs',
            'purchase_price' => $mrp * 0.6,
            'mrp' => $mrp,
            'labor_charge' => 0,
            'gst_rate' => 18,
            'current_stock_qty' => $stock,
            'reorder_level' => 5,
        ]);
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'invoice_date' => '2026-08-03',
            'items' => [
                ['spare_part_id' => 1, 'quantity' => 2, 'mrp' => 50, 'discount_percent' => 0],
            ],
        ], $overrides);
    }

    public function test_store_creates_anonymous_quick_sale_invoice()
    {
        $user = $this->makeUser();
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 20);

        $this->actingAs($user)
            ->post(route('counter-invoices.store'), [
                'invoice_date' => '2026-08-03',
                'items' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 50, 'discount_percent' => 0],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = CounterInvoice::first();

        $this->assertNotNull($invoice);
        $this->assertStringStartsWith('CI-', $invoice->invoice_no);
        $this->assertNull($invoice->customer_id);
        $this->assertNull($invoice->walkin_name);
        $this->assertNull($invoice->walkin_mobile);

        $this->assertCount(1, $invoice->items);
        $this->assertEquals(100, (float) $invoice->items->sum('line_total'));
        $this->assertEquals(0, (float) $invoice->cgst_amount);
        $this->assertEqualsWithDelta(100, (float) $invoice->grand_total, 0.01);
        $this->assertEquals(18, (int) $partA->fresh()->current_stock_qty);
    }

    public function test_store_accepts_radio_without_customer_selected()
    {
        $user = $this->makeUser();
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 20);

        // Radio toggle set to "existing" but no customer actually chosen -> still a valid anonymous sale
        $this->actingAs($user)
            ->post(route('counter-invoices.store'), [
                'invoice_date' => '2026-08-03',
                'customer_type' => 'existing',
                'customer_id' => '',
                'items' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 1, 'mrp' => 50, 'discount_percent' => 0],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = CounterInvoice::first();
        $this->assertNotNull($invoice);
        $this->assertNull($invoice->customer_id);
        $this->assertNull($invoice->walkin_name);
    }

    public function test_store_creates_invoice_for_existing_customer()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'Dhruval', 'mobile' => '9000000001', 'customer_type' => 'regular']);
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 20);

        $this->actingAs($user)
            ->post(route('counter-invoices.store'), [
                'invoice_date' => '2026-08-03',
                'customer_type' => 'existing',
                'customer_id' => $customer->id,
                'items' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 50, 'discount_percent' => 0],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = CounterInvoice::first();
        $this->assertNotNull($invoice);
        $this->assertEquals($customer->id, $invoice->customer_id);
        $this->assertNull($invoice->walkin_name);
    }

    public function test_store_applies_discount_before_gst_on_every_line()
    {
        $user = $this->makeUser();
        $part = $this->makePart('BRK-01', 'Brake Shoe', 100, 20);

        $this->actingAs($user)
            ->post(route('counter-invoices.store'), [
                'invoice_date' => '2026-08-03',
                'gst_applicable' => '1',
                'discount_percent' => '10',
                'items' => [
                    ['spare_part_id' => $part->id, 'quantity' => 1, 'mrp' => 100, 'discount_percent' => 0],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = CounterInvoice::first();
        $this->assertNotNull($invoice);
        $this->assertEquals(10.00, (float) $invoice->discount_percent);
        $this->assertEquals(10.00, (float) $invoice->discount_amount);
        // Line 100 discounted to 90 -> GST @18% = 16.20 -> 8.10/8.10
        $this->assertEquals(8.10, (float) $invoice->cgst_amount);
        $this->assertEquals(8.10, (float) $invoice->sgst_amount);
        // 90 + 16.20 = 106.20 -> rounds to 106.00, round-off -0.20
        $this->assertEquals(-0.20, (float) $invoice->round_off_amount);
        $this->assertEquals(106.00, (float) $invoice->grand_total);
    }

    public function test_store_rounds_off_paise_total_to_nearest_rupee()
    {
        $user = $this->makeUser();
        $part = $this->makePart('BRK-01', 'Brake Shoe', 99, 20);

        $this->actingAs($user)
            ->post(route('counter-invoices.store'), [
                'invoice_date' => '2026-08-03',
                'gst_applicable' => '1',
                'discount_percent' => '10',
                'items' => [
                    ['spare_part_id' => $part->id, 'quantity' => 1, 'mrp' => 99, 'discount_percent' => 0],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = CounterInvoice::first();
        $this->assertNotNull($invoice);
        $this->assertEquals(9.90, (float) $invoice->discount_amount);
        // Taxable 89.10 @18% = 16.038 -> halves rounded to 8.02/8.02 (total 16.04)
        $this->assertEquals(8.02, (float) $invoice->cgst_amount);
        $this->assertEquals(8.02, (float) $invoice->sgst_amount);
        // 89.10 + 16.04 = 105.14 -> rounds to 105.00, round-off -0.14
        $this->assertEquals(-0.14, (float) $invoice->round_off_amount);
        $this->assertEquals(105.00, (float) $invoice->grand_total);
    }

    public function test_store_creates_walkin_invoice()
    {
        $user = $this->makeUser();
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 20);

        $this->actingAs($user)
            ->post(route('counter-invoices.store'), [
                'invoice_date' => '2026-08-03',
                'customer_type' => 'walkin',
                'walkin_name' => 'Walk-in Buyer',
                'walkin_mobile' => '9876500000',
                'items' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 1, 'mrp' => 50, 'discount_percent' => 0],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = CounterInvoice::first();
        $this->assertNotNull($invoice);
        $this->assertNull($invoice->customer_id);
        $this->assertEquals('Walk-in Buyer', $invoice->walkin_name);
        $this->assertEquals('9876500000', $invoice->walkin_mobile);
    }

    public function test_update_syncs_items_recalculates_totals_and_adjusts_stock()
    {
        $user = $this->makeUser();
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 20);
        $partB = $this->makePart('BAT-01', 'Battery Cell', 100, 20);
        $partC = $this->makePart('MOT-01', 'Motor Spares', 30, 20);

        $invoice = CounterInvoice::create([
            'invoice_date' => '2026-08-03',
            'gst_applicable' => false,
        ]);

        // Initial population: A qty 2 (mrp 50) + B qty 1 (mrp 100, 10% discount)
        $this->actingAs($user)
            ->put(route('counter-invoices.update', $invoice), [
                'invoice_date' => '2026-08-03',
                'items' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 2, 'mrp' => 50, 'discount_percent' => 0],
                    ['spare_part_id' => $partB->id, 'quantity' => 1, 'mrp' => 100, 'discount_percent' => 10],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(18, (int) $partA->fresh()->current_stock_qty);
        $this->assertEquals(19, (int) $partB->fresh()->current_stock_qty);

        // Now edit: remove part B, change A qty 2->5, add part C qty 3, enable GST
        $this->actingAs($user)
            ->put(route('counter-invoices.update', $invoice), [
                'invoice_date' => '2026-08-04',
                'gst_applicable' => '1',
                'payment_mode' => 'cash',
                'items' => [
                    ['spare_part_id' => $partA->id, 'quantity' => 5, 'mrp' => 50, 'discount_percent' => 0],
                    ['spare_part_id' => $partC->id, 'quantity' => 3, 'mrp' => 30, 'discount_percent' => 0],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice->refresh();

        $this->assertCount(2, $invoice->items);
        $this->assertSame([$partA->id, $partC->id], $invoice->items->pluck('spare_part_id')->sort()->values()->toArray());
        $this->assertEquals(250, (float) $invoice->items->firstWhere('spare_part_id', $partA->id)->line_total);
        $this->assertEquals(90, (float) $invoice->items->firstWhere('spare_part_id', $partC->id)->line_total);

        // Subtotal 340 -> cgst/sgst 30.60 each -> pre-rounding 401.20 -> rounds to 401.00, round-off -0.20
        $this->assertEqualsWithDelta(30.60, (float) $invoice->cgst_amount, 0.01);
        $this->assertEqualsWithDelta(30.60, (float) $invoice->sgst_amount, 0.01);
        $this->assertEqualsWithDelta(-0.20, (float) $invoice->round_off_amount, 0.01);
        $this->assertEqualsWithDelta(401.00, (float) $invoice->grand_total, 0.01);
        $this->assertEquals('cash', $invoice->payment_mode);
        $this->assertEquals('2026-08-04', $invoice->invoice_date->format('Y-m-d'));

        // Old B stock returned, A re-deducted to 15, C deducted to 17
        $this->assertEquals(15, (int) $partA->fresh()->current_stock_qty);
        $this->assertEquals(20, (int) $partB->fresh()->current_stock_qty);
        $this->assertEquals(17, (int) $partC->fresh()->current_stock_qty);
    }

    public function test_create_and_edit_pages_render()
    {
        $user = $this->makeUser();
        $partA = $this->makePart('BRK-01', 'Brake Shoe', 50, 20);

        $this->actingAs($user)->get(route('counter-invoices.create'))->assertOk();

        $invoice = CounterInvoice::create([
            'invoice_date' => '2026-08-03',
            'customer_id' => null,
            'gst_applicable' => false,
        ]);
        $invoice->items()->create([
            'spare_part_id' => $partA->id,
            'quantity' => 2,
            'mrp' => 50,
            'discount_percent' => 0,
            'line_total' => 100,
        ]);

        $this->actingAs($user)->get(route('counter-invoices.edit', $invoice))->assertOk();
    }
}
