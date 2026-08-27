<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JobCard;
use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataTableSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ------------------------------------------------------------- helpers

    /**
     * Assertions shared by every module using the selection pattern: the
     * checkboxes and select-all wire into tableSelection state, the bar gates
     * on selectedCount and reveals single-record actions only at exactly one.
     */
    private function assertSelectionPattern(string $html): void
    {
        // The broken object-spread merge must never be used; getters are
        // copied by descriptor via mergeAlpineData (or the scope uses the
        // direct, unmerged tableSelection form).
        $this->assertStringNotContainsString('...tableSelection(', $html);

        // Per-row checkbox wiring.
        $this->assertStringContainsString(
            ":checked=\"isSelected(\$el.getAttribute('data-row-id'))\"",
            $html
        );
        $this->assertStringContainsString(
            "@change=\"toggleRow(\$el.getAttribute('data-row-id'))\"",
            $html
        );

        // Header select-all: checked via allSelected, toggles the page, and
        // becomes indeterminate when only some rows are selected.
        $this->assertStringContainsString(':checked="allSelected"', $html);
        $this->assertStringContainsString('@change="toggleAll()"', $html);
        $this->assertStringContainsString(
            'x-effect="$el.indeterminate = someSelected && !allSelected"',
            $html
        );

        // Contextual bar: hidden at zero, single-record actions only at one.
        $this->assertStringContainsString('x-show="selectedCount > 0"', $html);
        $this->assertStringContainsString('x-show="selectedCount === 1"', $html);
        $this->assertStringContainsString('x-text="selectedCount"', $html);

        // The shared "Export selected" bulk action (CSV of the selection) must
        // be present on every module table.
        $this->assertStringContainsString('Export selected', $html);
    }

    private function assertModalOpeners(string $html, string $modalName): void
    {
        // Bulk delete button dispatches open-modal for the scope's modal.
        $this->assertStringContainsString(
            "@click=\"\$dispatch('open-modal', '{$modalName}')\"",
            $html
        );
        // The confirmation dialog listens for that name.
        $this->assertStringContainsString(
            "x-on:open-modal.window=\"\$event.detail == '{$modalName}' ? show = true : null\"",
            $html
        );
    }

    // ------------------------------------------------------------- customers

    public function test_customers_index_uses_merged_selection_pattern(): void
    {
        Customer::create([
            'first_name' => 'Aarav',
            'last_name' => 'Shah',
            'mobile' => '9876543210',
            'customer_type' => 'regular',
        ]);

        $response = $this->actingAs($this->user)->get(route('customers.index'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('x-data="mergeAlpineData(tableSelection({ bulkEndpoint:', $html);
        $this->assertStringContainsString('customers/bulk-delete', $html);
        $this->assertStringContainsString('customerList())', $html);
        $this->assertStringContainsString('data-row-id="1"', $html);

        $this->assertSelectionPattern($html);

        $this->assertModalOpeners($html, 'bulk-delete-confirm');

        // Single-record actions are rendered inside the bar's single slot.
        $this->assertStringContainsString('Create Job Card', $html);
        $this->assertStringContainsString('Add Vehicle', $html);
        $this->assertStringContainsString('Edit', $html);
    }

    // ------------------------------------------------------------- job cards

    public function test_job_cards_index_uses_merged_selection_pattern(): void
    {
        $customer = Customer::create([
            'first_name' => 'Meera',
            'last_name' => 'Patel',
            'mobile' => '9876501234',
            'customer_type' => 'regular',
        ]);

        $vehicle = Vehicle::create(['customer_id' => $customer->id]);

        JobCard::create([
            'job_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'received',
            'job_type' => 'paid_service',
            'grand_total' => null,
        ]);

        $response = $this->actingAs($this->user)->get(route('job-cards.index'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('x-data="mergeAlpineData(tableSelection({ bulkEndpoint:', $html);
        $this->assertStringContainsString('job-cards/bulk-delete', $html);
        $this->assertStringContainsString('jobCardList())', $html);
        $this->assertStringContainsString('data-row-id="1"', $html);

        $this->assertSelectionPattern($html);

        $this->assertModalOpeners($html, 'bulk-delete-confirm');
        $this->assertStringContainsString('Assign / Add Items', $html);
    }

    // ------------------------------------------------------------- spare parts

    public function test_spare_parts_table_uses_direct_selection_pattern(): void
    {
        SparePart::create([
            'part_name' => 'Brake Shoe Set',
            'part_code' => 'BRK-001',
            'category' => 'brakes',
            'unit' => 'set',
            'purchase_price' => 350,
            'mrp' => 450,
        ]);

        LabourMaster::create([
            'service_name' => 'General Service',
            'category' => 'general',
            'standard_rate' => 500,
        ]);

        $response = $this->actingAs($this->user)->get(route('inventory.index'));

        $response->assertOk();
        $html = $response->getContent();

        // The spare parts table uses the direct (unmerged) form — no module
        // state to combine — while still keeping its own modal name.
        $this->assertStringContainsString(
            'x-data="tableSelection({ bulkEndpoint:',
            $html
        );
        $this->assertStringContainsString('spare-parts/bulk-delete', $html);
        $this->assertStringContainsString('modalName: \'bulk-delete-confirm-parts\'', $html);
        $this->assertStringContainsString('data-row-id="1"', $html);

        $this->assertSelectionPattern($html);

        $this->assertModalOpeners($html, 'bulk-delete-confirm-parts');
    }
}
