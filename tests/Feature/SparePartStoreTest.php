<?php

namespace Tests\Feature;

use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SparePartStoreTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'part_name' => 'Brake Shoe',
            'category' => 'brake',
            'unit' => 'pcs',
            'purchase_price' => 30,
            'mrp' => 50,
            'labor_charge' => 10,
            'gst_rate' => 18,
            'current_stock_qty' => 20,
            'reorder_level' => 5,
        ], $overrides);
    }

    public function test_store_generates_a_numeric_code_per_prefix(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('spare-parts.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('spare-parts.store'), $this->payload(['part_name' => 'Battery Cell', 'category' => 'battery']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Each prefix gets its own counter starting at 001.
        $this->assertSame('BRA-001', SparePart::where('category', 'brake')->first()->part_code);
        $this->assertSame('BAT-001', SparePart::where('category', 'battery')->first()->part_code);
    }

    public function test_generated_code_continues_after_the_highest_numeric_suffix(): void
    {
        $user = $this->makeUser();

        SparePart::create([
            'part_name' => 'Old Brake Pad',
            'part_code' => 'BRA-999',
            'category' => 'brake',
            'unit' => 'pcs',
            'purchase_price' => 30,
            'mrp' => 50,
            'labor_charge' => 10,
            'gst_rate' => 18,
            'current_stock_qty' => 5,
            'reorder_level' => 2,
        ]);

        // Legacy non-numeric codes for the same prefix must be ignored.
        SparePart::create([
            'part_name' => 'Legacy Brake Pad',
            'part_code' => 'BRA-LEGACY-1',
            'category' => 'brake',
            'unit' => 'pcs',
            'purchase_price' => 30,
            'mrp' => 50,
            'labor_charge' => 10,
            'gst_rate' => 18,
            'current_stock_qty' => 5,
            'reorder_level' => 2,
        ]);

        $response = $this->actingAs($user)
            ->post(route('spare-parts.store'), $this->payload());

        $createdCode = SparePart::orderByDesc('id')->first()->part_code;

        $response->assertSessionHasNoErrors();
        $this->assertSame('BRA-1000', $createdCode);
    }

    public function test_store_returns_friendly_error_when_code_generation_collides(): void
    {
        $user = $this->makeUser();

        DB::statement("CREATE TRIGGER force_part_collision BEFORE INSERT ON spare_parts
            WHEN NEW.part_code LIKE 'BRA-%'
            BEGIN SELECT RAISE(ABORT, 'simulated unique constraint'); END");

        try {
            $this->actingAs($user)
                ->post(route('spare-parts.store'), $this->payload())
                ->assertRedirect()
                ->assertSessionHasErrors('error');

            $this->assertStringContainsString(
                'already exists. Please submit again.',
                session('errors')->first('error')
            );
            $this->assertDatabaseCount('spare_parts', 0);
        } finally {
            DB::statement('DROP TRIGGER force_part_collision');
        }
    }
}
