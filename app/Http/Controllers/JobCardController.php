<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesSearchFilterSort;
use App\Http\Controllers\Concerns\BulkDeletesRecords;
use App\Http\Controllers\Concerns\BulkExportsRecords;
use App\Http\Requests\FilterJobCardsRequest;
use App\Http\Requests\StoreJobCardRequest;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\JobCard;
use App\Models\JobCardComplaint;
use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Rules\DiscountWithinSubtotal;
use App\Services\GstCalculator;
use App\Services\PartPriceResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class JobCardController extends Controller
{
    use AppliesSearchFilterSort;
    use BulkDeletesRecords;
    use BulkExportsRecords;

    protected function bulkDeleteModel(): string
    {
        return JobCard::class;
    }

    protected function bulkExportEntity(): string
    {
        return 'job_cards';
    }

    /**
     * Job Cards are SOFT-deleted: line items and stock deductions stay
     * exactly as they are so a restored document keeps its history, and
     * payment records are preserved for audit. Only a forceDelete (not
     * exposed over HTTP) removes rows permanently.
     */
    protected function bulkDeleteDeleter(): ?callable
    {
        return null;
    }

    public function index(FilterJobCardsRequest $request)
    {
        $validated = $request->validated();

        $query = JobCard::with(['customer', 'vehicle.vehicleModel.vehicleBrand', 'assignedTechnician'])
            ->withSum('payments', 'amount')
            ->withExists('parts')
            ->withExists('labours');

        // Apply search
        if ($request->filled('search')) {
            $query = $this->applySearch(
                $query,
                $validated['search'],
                ['job_card_no', 'customer.display_name', 'vehicle.registration_no']
            );
        }

        // Filter by technician
        if ($request->filled('technician')) {
            $query->where('assigned_technician_id', $validated['technician']);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $validated['status']);
        }

        // Filter by vehicle brand
        if ($request->filled('brand')) {
            $query->whereHas('vehicle.vehicleModel.vehicleBrand', fn ($q) => $q->where('name', $validated['brand']));
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('job_date', '>=', $validated['from_date']);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('job_date', '<=', $validated['to_date']);
        }

        // Filter by payment status
        if ($request->filled('payment_status') && $validated['payment_status'] !== 'all') {
            $status = $validated['payment_status'];
            if ($status === 'paid') {
                $query->whereRaw('COALESCE(grand_total, 0) > 0 AND COALESCE(grand_total, 0) <= (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = job_cards.id AND payments.payable_type = ? AND payments.deleted_at IS NULL)', [JobCard::class]);
            } elseif ($status === 'partially_paid') {
                $query->whereRaw('COALESCE(grand_total, 0) > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = job_cards.id AND payments.payable_type = ? AND payments.deleted_at IS NULL) AND (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = job_cards.id AND payments.payable_type = ? AND payments.deleted_at IS NULL) > 0', [JobCard::class, JobCard::class]);
            } elseif ($status === 'unbilled') {
                $query->where(function ($q) {
                    $q->whereNull('grand_total')->orWhere('grand_total', '<=', 0);
                });
            } elseif ($status === 'unpaid') {
                $query->whereRaw('COALESCE(grand_total, 0) > 0 AND COALESCE(grand_total, 0) > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = job_cards.id AND payments.payable_type = ? AND payments.deleted_at IS NULL) AND (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = job_cards.id AND payments.payable_type = ? AND payments.deleted_at IS NULL) = 0', [JobCard::class, JobCard::class]);
            }
        }

        // Filter by job type
        if ($request->filled('job_type')) {
            $query->where('job_type', $validated['job_type']);
        }

        // Apply sorting
        $sortColumn = $validated['sort'] ?? 'job_date';
        $sortDirection = $validated['direction'] ?? 'desc';

        if ($sortColumn === 'payment_status_label') {
            // Sort by payment status (computed field)
            $query->orderByRaw('
                CASE 
                    WHEN COALESCE(grand_total, 0) <= 0 THEN 1
                    WHEN COALESCE(grand_total, 0) <= (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = job_cards.id AND payments.payable_type = "'.addslashes(JobCard::class).'" AND payments.deleted_at IS NULL) THEN 2
                    WHEN (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = job_cards.id AND payments.payable_type = "'.addslashes(JobCard::class).'" AND payments.deleted_at IS NULL) > 0 THEN 3
                    ELSE 4
                END '.$sortDirection
            );
        } else {
            $query = $this->applySort(
                $query,
                $sortColumn,
                $sortDirection,
                ['job_date', 'status', 'assigned_technician_id', 'grand_total'],
                ['column' => 'job_date', 'direction' => 'desc']
            );
        }

        $perPage = $this->getPerPage($request);
        $jobCards = $query->paginate($perPage)->withQueryString();

        $technicians = Employee::active()->orderBy('name')->get();
        $brands = VehicleBrand::query()->orderBy('name')->pluck('name');

        return view('job_cards.index', compact('jobCards', 'technicians', 'brands'));
    }

    public function create(Request $request)
    {
        // No full-table downloads: the customer is picked through a searchable
        // combobox hitting customers/search, and only the selected customer's
        // vehicles are loaded.
        $customerId = $request->old('customer_id', $request->integer('customer_id')) ?: null;
        $vehicleId = $request->old('vehicle_id', $request->integer('vehicle_id')) ?: null;

        $initialCustomer = $customerId ? Customer::find($customerId) : null;
        $initialVehicles = collect();

        if ($initialCustomer) {
            // Only pre-select a vehicle that actually belongs to the given customer.
            if ($vehicleId && ! Vehicle::where('id', $vehicleId)->where('customer_id', $initialCustomer->id)->exists()) {
                $vehicleId = null;
            }
            $initialVehicles = $initialCustomer->vehicles()
                ->with(['vehicleModel.vehicleBrand'])
                ->orderBy('registration_no')
                ->get();
        }

        $initialData = [
            'customer_id' => $customerId ? (string) $customerId : '',
            'vehicle_id' => $vehicleId ? (string) $vehicleId : '',
            'complaints' => [],
        ];

        return view('job_cards.create', compact('initialCustomer', 'initialVehicles', 'initialData'));
    }

    public function store(StoreJobCardRequest $request)
    {
        $validated = $request->validated();

        // Server-side check: the selected vehicle must belong to the selected customer
        $vehicle = Vehicle::find($validated['vehicle_id']);
        if ($vehicle && $vehicle->customer_id != $validated['customer_id']) {
            return back()
                ->withErrors(['vehicle_id' => 'The selected vehicle does not belong to the selected customer.'])
                ->withInput();
        }

        try {
            DB::beginTransaction();

            // Numbering derives from MAX(suffix) + 1 with the unique index as
            // the final safety net; a lost race lands here as a QueryException
            // instead of a raw 500.
            $jobCard = JobCard::create([
                'job_date' => $validated['job_date'],
                'customer_id' => $validated['customer_id'],
                'vehicle_id' => $validated['vehicle_id'],
                'odometer_reading_km' => $validated['odometer_reading_km'] ?? null,
                'job_type' => $validated['job_type'],
                'promised_delivery_date' => $validated['promised_delivery_date'] ?? null,
                'promised_delivery_time' => $validated['promised_delivery_time'] ?? null,
                'status' => 'received',
                'remarks' => $validated['remarks'] ?? null,
            ]);

            if (! empty($validated['complaints'])) {
                foreach ($validated['complaints'] as $complaint) {
                    $jobCard->complaints()->create([
                        'description' => $complaint['description'],
                    ]);
                }
            }

            DB::commit();
        } catch (QueryException $e) {
            DB::rollBack();
            Log::warning('Job card numbering collision, retry required.', ['error' => $e->getMessage()]);

            return back()->withInput()->withErrors([
                'error' => 'Another job card was saved at the same moment and took the next job card number. Please submit again.',
            ]);
        }

        return redirect()->route('job-cards.assign', $jobCard)
            ->with('success', 'Job Card '.$jobCard->job_card_no.' created. Assign a technician and add parts / labour to bill.');
    }

    public function show(JobCard $jobCard)
    {
        $jobCard->load(['customer', 'vehicle.vehicleModel.vehicleBrand', 'assignedTechnician', 'supervisor',
            'complaints', 'parts.sparePart', 'labours.labourMaster', 'labours.technician', 'payments']);

        return view('job_cards.show', compact('jobCard'));
    }

    public function generateInvoice(JobCard $jobCard)
    {
        return $this->generateDocument($jobCard, 'invoice');
    }

    public function generateEstimate(JobCard $jobCard)
    {
        return $this->generateDocument($jobCard, 'estimate');
    }

    /**
     * Stream either a TAX INVOICE or an ESTIMATE PDF built from the same
     * Job Card data. The only differences are the document header text and a
     * few wording tweaks in the shared pdf.job-card template, controlled by
     * $documentType ('invoice' | 'estimate').
     *
     * Generation is recorded for audit purposes via invoice_generated_at /
     * estimate_generated_at but never locks the Job Card from further edits.
     */
    private function generateDocument(JobCard $jobCard, string $documentType)
    {
        if ($jobCard->parts()->count() + $jobCard->labours()->count() === 0) {
            return back()->with('error', 'Add at least one part or labour line before generating a document.');
        }

        $jobCard->load(['customer', 'vehicle', 'assignedTechnician', 'supervisor',
            'complaints', 'parts.sparePart', 'labours.labourMaster', 'labours.technician']);

        $filename = $documentType === 'invoice'
            ? 'TAX-INVOICE-'.$jobCard->job_card_no.'.pdf'
            : 'ESTIMATE-'.$jobCard->job_card_no.'.pdf';

        // Render first, record second: the audit timestamp must only be set
        // when a PDF was actually produced. Rendering to string first ensures
        // dompdf errors do not leave a timestamp without a deliverable PDF.
        $pdf = Pdf::setBasePath(base_path())
            ->loadView('pdf.job-card', compact('jobCard', 'documentType'));

        // Force rendering now; if dompdf throws, the timestamp is never written.
        $pdf->output();

        $jobCard->update([
            $documentType === 'invoice' ? 'invoice_generated_at' : 'estimate_generated_at' => now(),
        ]);

        return $pdf->download($filename);
    }

    public function assign(JobCard $jobCard)
    {
        $jobCard->load(['customer', 'vehicle', 'complaints', 'parts.sparePart', 'labours.labourMaster']);
        $employees = Employee::active()->orderBy('name')->get();
        $labourMasters = LabourMaster::orderBy('service_name')->get();

        return view('job_cards.assign', compact('jobCard', 'employees', 'labourMasters'));
    }

    public function updateAssign(Request $request, JobCard $jobCard)
    {
        $validated = $request->validate([
            'assigned_technician_id' => 'nullable|exists:employees,id',
            'supervisor_id' => 'nullable|exists:employees,id',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'gst_applicable' => 'boolean',
            'parts' => 'nullable|array|max:50',
            'parts.*.spare_part_id' => 'required|exists:spare_parts,id',
            'parts.*.quantity' => 'required|integer|min:1',
            'parts.*.labor_charge' => 'required|numeric|min:0',
            'parts.*.under_warranty' => 'boolean',
            'labours' => 'nullable|array|max:50',
            'labours.*.labour_master_id' => 'required|exists:labour_masters,id',
            'labours.*.technician_id' => 'nullable|exists:employees,id',
            'labours.*.rate' => 'required|numeric|min:0',
            'labours.*.qty' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            // MRP is always taken from the part master, never from the client submission
            $parts = app(PartPriceResolver::class)->priceLines($validated['parts'] ?? []);

            // STOCK VALIDATION DISABLED: No stock checking because Suppliers module is not integrated
            // Uncomment below to re-enable when Suppliers module is integrated:
            //
            // $stockErrors = app(StockValidator::class)->validate(
            //     $parts,
            //     $jobCard,
            //     'parts'
            // );
            // if (! empty($stockErrors)) {
            //     DB::rollBack();
            //     return back()->withErrors($stockErrors)->withInput();
            // }

            // The subtotal is only known once the lines are resolved. Reject a
            // discount (percent-derived or flat amount) that would exceed it,
            // otherwise GstCalculator yields a negative grand_total.
            $lines = $this->buildGstLines($parts, $validated['labours'] ?? []);
            $subtotal = collect($lines)->sum('line_total');

            $discountValidator = Validator::make(
                ['discount_amount' => $validated['discount_amount'] ?? 0],
                ['discount_amount' => ['nullable', 'numeric', 'min:0', new DiscountWithinSubtotal($subtotal, (float) ($validated['discount_percent'] ?? 0))]],
                [],
                ['discount_amount' => 'Discount Amount']
            );
            if ($discountValidator->fails()) {
                DB::rollBack();

                return back()->withErrors($discountValidator)->withInput();
            }

            // When the submitted lines are byte-for-byte what is already stored,
            // skip the destructive delete-and-recreate cycle below: stock avoids
            // the return/deduct round trip and unchanged rows keep their IDs.
            $linesUnchanged = $this->submittedLinesMatchExisting($jobCard, $parts, $validated['labours'] ?? []);

            $calculations = app(GstCalculator::class)->calculate(
                $lines,
                (bool) ($validated['gst_applicable'] ?? false),
                (float) ($validated['discount_percent'] ?? 0),
                (float) ($validated['discount_amount'] ?? 0),
            );

            if (! $linesUnchanged) {
                // Delete existing line items. Parts must be deleted one-by-one so the
                // JobCardPart::deleting boot event returns stock to inventory.
                foreach ($jobCard->parts as $part) {
                    $part->delete();
                }
                $jobCard->labours()->delete();
            }

            $jobCard->update([
                'assigned_technician_id' => $validated['assigned_technician_id'] ?? null,
                'supervisor_id' => $validated['supervisor_id'] ?? null,
                'discount_percent' => $calculations['discount_percent'],
                'discount_amount' => $calculations['discount_amount'],
                'gst_applicable' => (bool) ($validated['gst_applicable'] ?? false),
                'cgst_amount' => $calculations['cgst_amount'],
                'sgst_amount' => $calculations['sgst_amount'],
                'round_off_amount' => $calculations['round_off_amount'],
                'grand_total' => $calculations['grand_total'],
            ]);

            // Save parts (stock deduction happens in model boot)
            if (! $linesUnchanged && ! empty($parts)) {
                foreach ($parts as $part) {
                    $underWarranty = isset($part['under_warranty']) && $part['under_warranty'];
                    $lineTotal = $underWarranty ? 0 : ($part['quantity'] * ($part['mrp'] + $part['labor_charge']));

                    $jobCard->parts()->create([
                        'spare_part_id' => $part['spare_part_id'],
                        'quantity' => $part['quantity'],
                        'mrp' => $part['mrp'],
                        'labor_charge' => $part['labor_charge'],
                        'under_warranty' => $underWarranty,
                        'line_total' => $lineTotal,
                    ]);
                }
            }

            // Save labours
            if (! $linesUnchanged && ! empty($validated['labours'])) {
                foreach ($validated['labours'] as $labour) {
                    $lineTotal = $labour['rate'] * $labour['qty'];
                    $jobCard->labours()->create([
                        'labour_master_id' => $labour['labour_master_id'],
                        'technician_id' => $labour['technician_id'] ?? null,
                        'rate' => $labour['rate'],
                        'qty' => $labour['qty'],
                        'line_total' => $lineTotal,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('job-cards.show', $jobCard)->with('success', 'Job Card assigned and billed.');
        } catch (RuntimeException|QueryException $e) {
            // Expected failure modes: stock guards (RuntimeException) and
            // database constraint violations (QueryException). Anything else
            // is a bug and should bubble to the exception handler.
            DB::rollBack();
            Log::error($e);

            return back()->withInput()->withErrors(['error' => 'Something went wrong saving this record. Please try again.']);
        }
    }

    public function edit(JobCard $jobCard)
    {
        $jobCard->load(['customer', 'complaints']);

        $initialCustomer = $jobCard->customer;
        $initialVehicles = $initialCustomer
            ? $initialCustomer->vehicles()->with(['vehicleModel.vehicleBrand'])->orderBy('registration_no')->get()
            : collect();

        return view('job_cards.edit', compact('jobCard', 'initialCustomer', 'initialVehicles'));
    }

    public function update(Request $request, JobCard $jobCard)
    {
        $validated = $request->validate([
            'job_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'odometer_reading_km' => 'nullable|integer|min:0',
            'job_type' => 'required|in:'.implode(',', array_keys(config('garage.option_lists.job_type'))),
            'promised_delivery_date' => 'nullable|date|after_or_equal:job_date',
            'promised_delivery_time' => 'nullable|date_format:H:i',
            'status' => 'required|in:'.implode(',', array_keys(config('garage.option_lists.job_card_status'))),
            'remarks' => 'nullable|string',
            'complaints' => 'nullable|array|max:20',
            'complaints.*.description' => 'required|string',
        ]);

        // Server-side check: the selected vehicle must belong to the selected customer
        $vehicle = Vehicle::find($validated['vehicle_id']);
        if ($vehicle && $vehicle->customer_id != $validated['customer_id']) {
            return back()
                ->withErrors(['vehicle_id' => 'The selected vehicle does not belong to the selected customer.'])
                ->withInput();
        }

        // A billed card is a financial record: its customer / vehicle
        // attribution must stay frozen. Other fields (status, remarks,
        // complaints...) may still be edited.
        if ($jobCard->is_billed && (
            $jobCard->customer_id != $validated['customer_id']
            || $jobCard->vehicle_id != $validated['vehicle_id']
        )) {
            return back()
                ->withErrors(['customer_id' => 'This job card has been billed, so its customer and vehicle can no longer be changed.'])
                ->withInput();
        }

        $jobCard->update([
            'job_date' => $validated['job_date'],
            'customer_id' => $validated['customer_id'],
            'vehicle_id' => $validated['vehicle_id'],
            'odometer_reading_km' => $validated['odometer_reading_km'] ?? null,
            'job_type' => $validated['job_type'],
            'promised_delivery_date' => $validated['promised_delivery_date'] ?? null,
            'promised_delivery_time' => $validated['promised_delivery_time'] ?? null,
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
        ]);

        // Sync complaints by delta instead of wiping and recreating, so
        // unchanged rows keep their identity (IDs / any future references).
        $submittedDescriptions = collect($validated['complaints'] ?? [])
            ->pluck('description')
            ->map(fn ($description) => trim((string) $description))
            ->filter(fn ($description) => $description !== '')
            ->unique()
            ->values();

        $existingDescriptions = $jobCard->complaints()->pluck('description', 'id');

        $existingDescriptions->each(function ($description, $id) use ($submittedDescriptions) {
            if (! $submittedDescriptions->contains($description)) {
                JobCardComplaint::whereKey($id)->delete();
            }
        });

        $submittedDescriptions->each(function ($description) use ($jobCard, $existingDescriptions) {
            if (! $existingDescriptions->contains($description)) {
                $jobCard->complaints()->create(['description' => $description]);
            }
        });

        return redirect()->route('job-cards.show', $jobCard)->with('success', 'Job Card updated.');
    }

    public function destroy(JobCard $jobCard)
    {
        $this->authorize('admin');

        // Soft delete only: parts, stock deductions and payments stay put so
        // the document can be restored with its full history.
        $jobCard->delete();

        return redirect()->route('job-cards.index')->with('success', 'Job Card deleted.');
    }

    /**
     * Compare submitted lines against stored ones (order-insensitive) so an
     * idempotent re-save can skip the destructive rewrite entirely. MRP is
     * excluded from the comparison: it is always overwritten from the part
     * master regardless.
     */
    private function submittedLinesMatchExisting(JobCard $jobCard, array $parts, array $labours): bool
    {
        $money = fn ($value) => number_format((float) $value, 2, '.', '');

        $incomingParts = collect($parts)
            ->map(fn ($part) => [
                (int) $part['spare_part_id'],
                (int) $part['quantity'],
                $money($part['labor_charge']),
                ! empty($part['under_warranty']),
            ])
            ->sort()->values()->toJson();

        $storedParts = $jobCard->parts()->get()
            ->map(fn ($part) => [
                (int) $part->spare_part_id,
                (int) $part->quantity,
                $money($part->labor_charge),
                (bool) $part->under_warranty,
            ])
            ->sort()->values()->toJson();

        if ($incomingParts !== $storedParts) {
            return false;
        }

        $incomingLabours = collect($labours)
            ->map(fn ($labour) => [
                (int) $labour['labour_master_id'],
                (int) ($labour['technician_id'] ?? 0),
                $money($labour['rate']),
                $money($labour['qty']),
            ])
            ->sort()->values()->toJson();

        $storedLabours = $jobCard->labours()->get()
            ->map(fn ($labour) => [
                (int) $labour->labour_master_id,
                (int) ($labour->technician_id ?? 0),
                $money($labour->rate),
                $money($labour->qty),
            ])
            ->sort()->values()->toJson();

        return $incomingLabours === $storedLabours;
    }

    private function buildGstLines(array $parts, array $labours): array
    {
        $partIds = collect($parts)->pluck('spare_part_id')->unique();
        $spareParts = $partIds->isEmpty() ? collect() : SparePart::whereIn('id', $partIds)->get()->keyBy('id');

        $labourIds = collect($labours)->pluck('labour_master_id')->unique();
        $laboursByMaster = $labourIds->isEmpty() ? collect() : LabourMaster::whereIn('id', $labourIds)->get()->keyBy('id');

        $lines = [];

        foreach ($parts as $part) {
            $underWarranty = isset($part['under_warranty']) && $part['under_warranty'];
            $lines[] = [
                'line_total' => $underWarranty ? 0 : ($part['quantity'] * ($part['mrp'] + $part['labor_charge'])),
                'gst_rate' => $spareParts[$part['spare_part_id']]->gst_rate ?? null,
            ];
        }

        foreach ($labours as $labour) {
            $master = $laboursByMaster[$labour['labour_master_id']] ?? null;
            $lines[] = [
                'line_total' => $labour['rate'] * $labour['qty'],
                'gst_rate' => ($master && $master->taxable) ? $master->gst_rate : null,
            ];
        }

        return $lines;
    }
}
