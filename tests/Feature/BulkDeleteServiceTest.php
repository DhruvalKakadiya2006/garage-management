<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JobCard;
use App\Services\BulkDeleteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class BulkDeleteServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

    private function makeCustomer(string $name, string $mobile): Customer
    {
        return Customer::create([
            'first_name' => $name,
            'mobile' => $mobile,
            'customer_type' => 'regular',
        ]);
    }

    private static int $regCounter = 0;

    private function makeLinkedJobCard(Customer $customer): JobCard
    {
        self::$regCounter++;
        $vehicle = $this->makeCatalogVehicle(
            $customer,
            'GJ-01-AB-'.str_pad((string) self::$regCounter, 4, '0', STR_PAD_LEFT),
            'Ola',
            'S1 Pro'
        );

        return JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
            'gst_applicable' => false,
        ]);
    }

    public function test_bulk_delete_removes_unlinked_records_and_skips_linked_ones(): void
    {
        $linked = $this->makeCustomer('Linked', '9000000001');
        $this->makeLinkedJobCard($linked);

        $freeOne = $this->makeCustomer('Free One', '9000000002');
        $freeTwo = $this->makeCustomer('Free Two', '9000000003');

        $result = app(BulkDeleteService::class)->delete(
            Customer::class,
            [$linked->id, $freeOne->id, $freeTwo->id]
        );

        $this->assertSame(2, $result['deleted_count']);
        $this->assertSame(1, $result['skipped_count']);
        $this->assertSame([(string) $linked->id], $result['skipped_ids']);
        $this->assertSame(2, count($result['deleted_ids']));
        $this->assertStringContainsString('2 deleted, 1 skipped', $result['message']);

        $this->assertDatabaseMissing('customers', ['id' => $freeOne->id]);
        $this->assertDatabaseMissing('customers', ['id' => $freeTwo->id]);
        $this->assertDatabaseHas('customers', ['id' => $linked->id]);
    }

    public function test_bulk_delete_with_all_linked_records_reports_everything_skipped(): void
    {
        $linkedOne = $this->makeCustomer('Linked One', '9000000004');
        $this->makeLinkedJobCard($linkedOne);
        $linkedTwo = $this->makeCustomer('Linked Two', '9000000005');
        $this->makeLinkedJobCard($linkedTwo);

        $result = app(BulkDeleteService::class)->delete(
            Customer::class,
            [$linkedOne->id, $linkedTwo->id]
        );

        $this->assertSame(0, $result['deleted_count']);
        $this->assertSame(2, $result['skipped_count']);
        $this->assertStringContainsString('No records deleted', $result['message']);

        $this->assertDatabaseHas('customers', ['id' => $linkedOne->id]);
        $this->assertDatabaseHas('customers', ['id' => $linkedTwo->id]);
    }

    public function test_bulk_delete_counts_missing_ids_as_skipped(): void
    {
        $free = $this->makeCustomer('Free One', '9000000006');

        $result = app(BulkDeleteService::class)->delete(Customer::class, [$free->id, 999999]);

        $this->assertSame(1, $result['deleted_count']);
        $this->assertSame(1, $result['skipped_count']);
        $this->assertSame(['999999'], $result['skipped_ids']);
    }

    public function test_bulk_delete_logs_the_reason_a_record_was_skipped(): void
    {
        Log::spy();

        $customer = $this->makeCustomer('Doomed', '9000000007');

        app(BulkDeleteService::class)->delete(
            Customer::class,
            [$customer->id],
            function (): void {
                throw new \RuntimeException('simulated protected record');
            }
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Bulk delete skipped a record.'
                    && $context['reason'] === 'simulated protected record';
            });
    }
}
