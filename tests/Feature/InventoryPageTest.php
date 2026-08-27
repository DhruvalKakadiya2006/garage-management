<?php

namespace Tests\Feature;

use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePart(string $code, string $name, int $stock = 10, ?string $createdAt = null): SparePart
    {
        $part = SparePart::create([
            'part_name' => $name,
            'part_code' => $code,
            'category' => 'battery',
            'unit' => 'pcs',
            'purchase_price' => 30,
            'mrp' => 50,
            'labor_charge' => 10,
            'gst_rate' => 18,
            'current_stock_qty' => $stock,
            'reorder_level' => 5,
        ]);

        if ($createdAt) {
            $part->forceFill(['created_at' => $createdAt])->save();
        }

        return $part;
    }

    private function makeLabour(string $service): LabourMaster
    {
        return LabourMaster::create([
            'service_name' => $service,
            'category' => 'general',
            'standard_rate' => 200,
            'taxable' => true,
        ]);
    }

    public function test_inventory_page_renders_both_tabs_and_their_tables()
    {
        $user = $this->makeUser();
        $part = $this->makePart('BRK-01', 'Brake Shoe', 10);
        $labour = $this->makeLabour('General Service');

        $this->actingAs($user)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Inventory')
            ->assertSee('Spare Parts')
            ->assertSee('Labour Services')
            ->assertSee($part->part_name)
            ->assertSee($labour->service_name);
    }

    public function test_inventory_page_respects_low_stock_filter()
    {
        $user = $this->makeUser();
        $low = $this->makePart('LOW-01', 'Low Stock Part', 2);
        $ok = $this->makePart('OK-01', 'Healthy Part', 50);

        $this->actingAs($user)
            ->get(route('inventory.index', ['low_stock' => 1]))
            ->assertOk()
            ->assertSee($low->part_name)
            ->assertDontSee($ok->part_name);
    }

    public function test_inventory_page_paginates_parts_and_labours_independently()
    {
        $user = $this->makeUser();

        for ($i = 1; $i <= 16; $i++) {
            $this->makePart(
                'P-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                "Part Number {$i}",
                10,
                now()->subMinutes(40 + (16 - $i) * 2)->toDateTimeString()
            );
        }

        $labour = $this->makeLabour('General Service');

        // Latest part (16) sits on page 1; page 2 holds the oldest part (1).
        // The labour table stays on its own page so both collections coexist.
        $this->actingAs($user)
            ->get(route('inventory.index', ['parts_page' => 2]))
            ->assertOk()
            ->assertSee('Part Number 1')
            ->assertDontSee('Part Number 16')
            ->assertSee($labour->service_name);
    }

    public function test_standalone_spare_parts_and_labour_master_pages_redirect_to_inventory_tabs()
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('spare-parts.index'))
            ->assertRedirect(route('inventory.index', ['tab' => 'parts']));

        $this->actingAs($user)
            ->get(route('labour-masters.index'))
            ->assertRedirect(route('inventory.index', ['tab' => 'labour']));
    }
}
