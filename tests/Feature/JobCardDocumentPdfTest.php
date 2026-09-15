<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsVehicleCatalog;
use Tests\TestCase;

class JobCardDocumentPdfTest extends TestCase
{
    use RefreshDatabase;
    use SeedsVehicleCatalog;

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
            'labor_charge' => 10,
            'hsn_code' => '8714',
            'gst_rate' => 18,
            'current_stock_qty' => $stock,
            'reorder_level' => 5,
        ]);
    }

    /**
     * Build a fully billed job card: 1 part line, 1 labour line, totals,
     * complaint. Subtotal 320, 10% discount (32), GST 18% on both lines.
     */
    private function makeBilledJobCard(): JobCard
    {
        $customer = Customer::create([
            'first_name' => 'Dhruval',
            'last_name' => 'Patel',
            'mobile' => '9000000001',
            'customer_type' => 'regular',
            'address' => '12 Ring Road',
            'city' => 'Surat',
            'gstin' => '24AAAAA0000A1Z5',
        ]);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-AB-1234', 'Ola', 'S1 Pro', 'Black');

        $part = $this->makePart('BRK-01', 'Brake Shoe', 50, 20);
        $labour = LabourMaster::create([
            'service_name' => 'General Service',
            'category' => 'general',
            'standard_rate' => 200,
            'taxable' => true,
            'gst_rate' => 18,
        ]);

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'odometer_reading_km' => 12000,
            'job_type' => 'paid_service',
            'status' => 'in_progress',
            'gst_applicable' => true,
            'discount_percent' => 10,
            'discount_amount' => 32,
            'cgst_amount' => 25.92,
            'sgst_amount' => 25.92,
            'round_off_amount' => 0.16,
            'grand_total' => 340,
        ]);

        $jobCard->complaints()->create(['description' => 'Brake noise while riding']);
        $jobCard->parts()->create([
            'spare_part_id' => $part->id,
            'quantity' => 2,
            'mrp' => 50,
            'labor_charge' => 10,
            'under_warranty' => false,
            'line_total' => 120,
        ]);
        $jobCard->labours()->create([
            'labour_master_id' => $labour->id,
            'technician_id' => null,
            'rate' => 200,
            'qty' => 1,
            'line_total' => 200,
        ]);

        return $jobCard;
    }

    public function test_generate_invoice_streams_pdf_and_records_timestamp()
    {
        $user = $this->makeUser();
        $jobCard = $this->makeBilledJobCard();

        $response = $this->actingAs($user)->post(route('job-cards.invoice', $jobCard));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
        $this->assertStringContainsString('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition') ?? '');
        $this->assertStringContainsString('TAX-INVOICE-JC-', $response->headers->get('content-disposition') ?? '');

        $jobCard->refresh();
        $this->assertNotNull($jobCard->invoice_generated_at);
        $this->assertNull($jobCard->estimate_generated_at);
    }

    public function test_generate_estimate_streams_pdf_and_records_timestamp()
    {
        $user = $this->makeUser();
        $jobCard = $this->makeBilledJobCard();

        $response = $this->actingAs($user)->post(route('job-cards.estimate', $jobCard));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
        $this->assertStringContainsString('%PDF-', $response->getContent());
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition') ?? '');
        $this->assertStringContainsString('ESTIMATE-JC-', $response->headers->get('content-disposition') ?? '');

        $jobCard->refresh();
        $this->assertNotNull($jobCard->estimate_generated_at);
        $this->assertNull($jobCard->invoice_generated_at);
    }

    public function test_shared_template_renders_document_specific_wording()
    {
        $jobCard = $this->makeBilledJobCard();
        $validity = config('garage.estimate_validity_days');

        $invoiceHtml = view('pdf.job-card', ['jobCard' => $jobCard, 'documentType' => 'invoice'])->render();
        $this->assertStringContainsString('TAX INVOICE', $invoiceHtml);
        $this->assertStringContainsString('Payment Terms', $invoiceHtml);
        $this->assertStringContainsString('Grand Total', $invoiceHtml);
        $this->assertStringContainsString('Brake Shoe', $invoiceHtml);
        $this->assertStringContainsString('General Service', $invoiceHtml);
        $this->assertStringContainsString('GSTIN:', $invoiceHtml);
        $this->assertStringNotContainsString('This estimate is valid', $invoiceHtml);

        $estimateHtml = view('pdf.job-card', ['jobCard' => $jobCard, 'documentType' => 'estimate'])->render();
        $this->assertStringContainsString('ESTIMATE', $estimateHtml);
        // Validity text removed per user request
        $this->assertStringNotContainsString("valid for {$validity} days", $estimateHtml);
        $this->assertStringContainsString('Estimated Total', $estimateHtml);
        // Subtitle removed per user request
        $this->assertStringNotContainsString('Proposed quotation', $estimateHtml);
        $this->assertStringNotContainsString('TAX INVOICE', $estimateHtml);
        $this->assertStringNotContainsString('Payment Terms', $estimateHtml);
    }

    public function test_company_terms_and_conditions_render_on_invoices_and_estimates(): void
    {
        $jobCard = $this->makeBilledJobCard();
        $terms = 'Goods once sold are not returnable.';

        CompanyProfile::profile()->update([
            'terms_and_conditions' => $terms,
        ]);

        $invoiceHtml = view('pdf.job-card', ['jobCard' => $jobCard, 'documentType' => 'invoice'])->render();
        $estimateHtml = view('pdf.job-card', ['jobCard' => $jobCard, 'documentType' => 'estimate'])->render();

        $this->assertStringContainsString($terms, $invoiceHtml);
        $this->assertStringContainsString($terms, $estimateHtml);
    }

    public function test_document_generation_requires_line_items()
    {
        $user = $this->makeUser();
        $customer = Customer::create(['first_name' => 'A', 'mobile' => '9000000002', 'customer_type' => 'regular']);
        $vehicle = $this->makeCatalogVehicle($customer, 'GJ-01-CD-5678', 'Ather', '450X');

        $jobCard = JobCard::create([
            'job_date' => '2026-08-03',
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'job_type' => 'paid_service',
            'status' => 'received',
        ]);

        $this->actingAs($user)->post(route('job-cards.invoice', $jobCard))
            ->assertRedirect()
            ->assertSessionHas('error', 'Add at least one part or labour line before generating a document.');

        $jobCard->refresh();
        $this->assertNull($jobCard->invoice_generated_at);
        $this->assertNull($jobCard->estimate_generated_at);
    }

    public function test_generating_document_does_not_lock_job_card_for_edits()
    {
        $user = $this->makeUser();
        $jobCard = $this->makeBilledJobCard();
        $extraPart = $this->makePart('BAT-01', 'Battery Cell', 100, 20);

        $this->actingAs($user)->post(route('job-cards.invoice', $jobCard))->assertOk();

        $this->actingAs($user)
            ->post(route('job-cards.assign-update', $jobCard), [
                'assigned_technician_id' => null,
                'parts' => [
                    ['spare_part_id' => $extraPart->id, 'quantity' => 1, 'mrp' => 100, 'labor_charge' => 0],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $jobCard->refresh();
        $this->assertSame(1, $jobCard->parts()->where('spare_part_id', $extraPart->id)->count());
        $this->assertNotNull($jobCard->invoice_generated_at);
    }
}
