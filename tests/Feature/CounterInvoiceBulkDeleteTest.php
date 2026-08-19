<?php

namespace Tests\Feature;

use App\Models\CounterInvoice;
use App\Models\Payment;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterInvoiceBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePart(string $code, string $name, int $stock): SparePart
    {
        return SparePart::create([
            'part_name' => $name,
            'part_code' => $code,
            'category' => 'battery',
            'unit' => 'pcs',
            'purchase_price' => 30,
            'mrp' => 50,
            'labor_charge' => 0,
            'gst_rate' => 18,
            'current_stock_qty' => $stock,
            'reorder_level' => 5,
        ]);
    }

    private function makeInvoice(bool $gst = false): CounterInvoice
    {
        return CounterInvoice::create([
            'invoice_date' => '2026-08-03',
            'walkin_name' => 'Walk-in Buyer',
            'gst_applicable' => $gst,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'cgst_amount' => 0,
            'sgst_amount' => 0,
            'round_off_amount' => 0,
            'grand_total' => 0,
        ]);
    }

    public function test_bulk_delete_counter_invoices_returns_item_stock(): void
    {
        $user = $this->makeUser();
        $part = $this->makePart('BRK-01', 'Brake Shoe', 10);

        $withItems = $this->makeInvoice();
        $withItems->items()->create([
            'spare_part_id' => $part->id,
            'quantity' => 3,
            'mrp' => 50,
            'discount_percent' => 0,
            'line_total' => 150,
        ]);

        $plain = $this->makeInvoice();

        $this->assertEquals(7, (int) $part->fresh()->current_stock_qty);

        $response = $this->actingAs($user)->postJson(route('counter-invoices.bulk-delete'), [
            'ids' => [$withItems->id, $plain->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('deleted_count', 2)
            ->assertJsonPath('skipped_count', 0)
            ->assertJsonPath('message', '2 deleted');

        $this->assertDatabaseMissing('counter_invoices', ['id' => $withItems->id]);
        $this->assertDatabaseMissing('counter_invoices', ['id' => $plain->id]);
        $this->assertDatabaseMissing('counter_invoice_items', ['counter_invoice_id' => $withItems->id]);
        $this->assertEquals(10, (int) $part->fresh()->current_stock_qty);
    }

    public function test_bulk_delete_counter_invoice_removes_related_payments(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice();
        $invoice->update(['grand_total' => 300]);

        Payment::create([
            'payable_id' => $invoice->id,
            'payable_type' => CounterInvoice::class,
            'amount' => 100,
            'payment_mode' => 'cash',
            'payment_date' => '2026-08-03',
        ]);

        $this->actingAs($user)
            ->postJson(route('counter-invoices.bulk-delete'), ['ids' => [$invoice->id]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 1)
            ->assertJsonPath('skipped_count', 0);

        $this->assertDatabaseMissing('counter_invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('payments', ['payable_id' => $invoice->id, 'payable_type' => CounterInvoice::class]);
    }

    public function test_bulk_delete_requires_ids_array(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->postJson(route('counter-invoices.bulk-delete'), ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}