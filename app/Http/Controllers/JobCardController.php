<?php

namespace App\Http\Controllers;

use App\Models\JobCard;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Employee;
use App\Models\SparePart;
use App\Models\LabourMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\GstCalculator;
use Barryvdh\DomPDF\Facade\Pdf;

class JobCardController extends Controller
{
    public function index(Request $request)
    {
        $query = JobCard::with(['customer', 'vehicle', 'assignedTechnician', 'parts', 'labours'])
            ->withSum('payments', 'amount');

        // Filter by technician
        if ($request->filled('technician')) {
            $query->where('assigned_technician_id', $request->technician);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by vehicle brand
        if ($request->filled('brand')) {
            $query->whereHas('vehicle', fn ($q) => $q->where('vehicle_brand', $request->brand));
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('job_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('job_date', '<=', $request->to_date);
        }

        $jobCards = $query->latest()->paginate(15)->withQueryString();
        $technicians = Employee::active()->orderBy('name')->get();
        $brands = Vehicle::query()->select('vehicle_brand')->distinct()->orderBy('vehicle_brand')->pluck('vehicle_brand');

        return view('job_cards.index', compact('jobCards', 'technicians', 'brands'));
    }

    public function create(Request $request)
    {
        $customers = Customer::orderBy('display_name')->get();
        $vehicles = Vehicle::with('customer')->orderBy('registration_no')->get();

        $customerId = $request->integer('customer_id') ?: null;
        $vehicleId = $request->integer('vehicle_id') ?: null;

        // Only pre-select a vehicle that actually belongs to the given customer.
        if ($customerId && $vehicleId) {
            $vehicleBelongsToCustomer = Vehicle::where('id', $vehicleId)
                ->where('customer_id', $customerId)
                ->exists();

            if (! $vehicleBelongsToCustomer) {
                $vehicleId = null;
            }
        }

        $initialData = [
            'customer_id' => $customerId ?: '',
            'vehicle_id' => $vehicleId ?: '',
            'complaints' => [],
        ];

        return view('job_cards.create', compact('customers', 'vehicles', 'initialData'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'job_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'odometer_reading_km' => 'nullable|integer|min:0',
            'job_type' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.job_type'))),
            'promised_delivery_date' => 'nullable|date',
            'promised_delivery_time' => 'nullable',
            'remarks' => 'nullable|string',
            'complaints' => 'nullable|array',
            'complaints.*.description' => 'required|string',
        ]);

        // Server-side check: the selected vehicle must belong to the selected customer
        $vehicle = Vehicle::find($validated['vehicle_id']);
        if ($vehicle && $vehicle->customer_id != $validated['customer_id']) {
            return back()
                ->withErrors(['vehicle_id' => 'The selected vehicle does not belong to the selected customer.'])
                ->withInput();
        }

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

        if (!empty($request->complaints)) {
            foreach ($request->complaints as $complaint) {
                $jobCard->complaints()->create([
                    'description' => $complaint['description'],
                ]);
            }
        }

        return redirect()->route('job-cards.assign', $jobCard)
            ->with('success', 'Job Card ' . $jobCard->job_card_no . ' created. Assign a technician and add parts / labour to bill.');
    }

    public function show(JobCard $jobCard)
    {
        $jobCard->load(['customer', 'vehicle', 'assignedTechnician', 'supervisor',
                        'complaints', 'parts.sparePart', 'labours.labourMaster', 'labours.technician']);
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

        $jobCard->update([
            $documentType === 'invoice' ? 'invoice_generated_at' : 'estimate_generated_at' => now(),
        ]);

        $filename = $documentType === 'invoice'
            ? 'TAX-INVOICE-' . $jobCard->job_card_no . '.pdf'
            : 'ESTIMATE-' . $jobCard->job_card_no . '.pdf';

        $pdf = Pdf::loadView('pdf.job-card', compact('jobCard', 'documentType'));

        return $pdf->download($filename);
    }

    public function assign(JobCard $jobCard)
    {
        $jobCard->load(['customer', 'vehicle', 'complaints', 'parts.sparePart', 'labours.labourMaster']);
        $employees = Employee::active()->orderBy('name')->get();
        $spareParts = SparePart::orderBy('part_name')->get();
        $labourMasters = LabourMaster::orderBy('service_name')->get();

        return view('job_cards.assign', compact('jobCard', 'employees', 'spareParts', 'labourMasters'));
    }

    public function updateAssign(Request $request, JobCard $jobCard)
    {
        $validated = $request->validate([
            'assigned_technician_id' => 'nullable|exists:employees,id',
            'supervisor_id' => 'nullable|exists:employees,id',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'gst_applicable' => 'boolean',
            'parts' => 'nullable|array',
            'parts.*.spare_part_id' => 'required|exists:spare_parts,id',
            'parts.*.quantity' => 'required|integer|min:1',
            'parts.*.mrp' => 'required|numeric|min:0',
            'parts.*.labor_charge' => 'required|numeric|min:0',
            'parts.*.under_warranty' => 'boolean',
            'labours' => 'nullable|array',
            'labours.*.labour_master_id' => 'required|exists:labour_masters,id',
            'labours.*.technician_id' => 'nullable|exists:employees,id',
            'labours.*.rate' => 'required|numeric|min:0',
            'labours.*.qty' => 'required|numeric|min:0',
        ]);

        // Stock check accounting for quantity this job card already reserves
        $stockErrors = $this->validatePartsStock($request, $jobCard);
        if (!empty($stockErrors)) {
            return back()->withErrors($stockErrors)->withInput();
        }

        DB::beginTransaction();
        try {
            $calculations = app(GstCalculator::class)->calculate(
                $this->buildGstLines($request),
                $request->has('gst_applicable'),
                $request->discount_percent ?? 0,
                $request->discount_amount ?? 0,
            );

            // Delete existing line items. Parts must be deleted one-by-one so the
            // JobCardPart::deleting boot event returns stock to inventory.
            foreach ($jobCard->parts as $part) {
                $part->delete();
            }
            $jobCard->labours()->delete();

            $jobCard->update([
                'assigned_technician_id' => $validated['assigned_technician_id'] ?? null,
                'supervisor_id' => $validated['supervisor_id'] ?? null,
                'discount_percent' => $calculations['discount_percent'],
                'discount_amount' => $calculations['discount_amount'],
                'gst_applicable' => $request->has('gst_applicable'),
                'cgst_amount' => $calculations['cgst_amount'],
                'sgst_amount' => $calculations['sgst_amount'],
                'round_off_amount' => $calculations['round_off_amount'],
                'grand_total' => $calculations['grand_total'],
            ]);

            // Save parts (stock deduction happens in model boot)
            if (!empty($request->parts)) {
                foreach ($request->parts as $part) {
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
            if (!empty($request->labours)) {
                foreach ($request->labours as $labour) {
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
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to save assignment: ' . $e->getMessage()]);
        }
    }

    public function edit(JobCard $jobCard)
    {
        $jobCard->load(['complaints']);
        $customers = Customer::orderBy('display_name')->get();
        $vehicles = Vehicle::with('customer')->orderBy('registration_no')->get();

        return view('job_cards.edit', compact('jobCard', 'customers', 'vehicles'));
    }

    public function update(Request $request, JobCard $jobCard)
    {
        $validated = $request->validate([
            'job_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'odometer_reading_km' => 'nullable|integer|min:0',
            'job_type' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.job_type'))),
            'promised_delivery_date' => 'nullable|date',
            'promised_delivery_time' => 'nullable',
            'status' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.job_card_status'))),
            'remarks' => 'nullable|string',
            'complaints' => 'nullable|array',
            'complaints.*.description' => 'required|string',
        ]);

        // Server-side check: the selected vehicle must belong to the selected customer
        $vehicle = Vehicle::find($validated['vehicle_id']);
        if ($vehicle && $vehicle->customer_id != $validated['customer_id']) {
            return back()
                ->withErrors(['vehicle_id' => 'The selected vehicle does not belong to the selected customer.'])
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

        $jobCard->complaints()->delete();
        if (!empty($request->complaints)) {
            foreach ($request->complaints as $complaint) {
                $jobCard->complaints()->create([
                    'description' => $complaint['description'],
                ]);
            }
        }

        return redirect()->route('job-cards.show', $jobCard)->with('success', 'Job Card updated.');
    }

    public function destroy(JobCard $jobCard)
    {
        DB::beginTransaction();
        try {
            // Delete parts one-by-one so JobCardPart::deleting returns stock.
            // The job_card_parts FK is restrict, so the parent cannot be deleted
            // while any part rows remain — this guarantees stock is returned.
            foreach ($jobCard->parts as $part) {
                $part->delete();
            }

            $jobCard->delete();

            DB::commit();
            return redirect()->route('job-cards.index')->with('success', 'Job Card deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to delete job card: ' . $e->getMessage()]);
        }
    }

    private function validatePartsStock(Request $request, ?JobCard $jobCard): array
    {
        $errors = [];
        $parts = $request->input('parts', []);

        if (empty($parts)) {
            return $errors;
        }

        // Aggregate requested quantity per part across all lines in this request
        $usageByPart = [];
        foreach ($parts as $index => $part) {
            $partId = (int) $part['spare_part_id'];
            $usageByPart[$partId] = ($usageByPart[$partId] ?? 0) + (int) $part['quantity'];
        }

        foreach ($usageByPart as $partId => $requestedQty) {
            $sparePart = SparePart::find($partId);
            if (!$sparePart) {
                continue;
            }

            $available = (int) $sparePart->current_stock_qty;
            if ($jobCard) {
                // Quantity this job card already reserves for the same part
                $available += (int) $jobCard->parts()->where('spare_part_id', $partId)->sum('quantity');
            }

            if ($requestedQty > $available) {
                $index = collect($parts)->search(fn ($part) => (int) $part['spare_part_id'] === $partId);
                $errors["parts.{$index}.quantity"] = "Insufficient stock for '{$sparePart->part_name}'. Requested {$requestedQty}, only {$available} available.";
            }
        }

        return $errors;
    }

    private function buildGstLines(Request $request): array
    {
        $partIds = collect($request->parts ?? [])->pluck('spare_part_id')->unique();
        $parts = $partIds->isEmpty() ? collect() : SparePart::whereIn('id', $partIds)->get()->keyBy('id');

        $labourIds = collect($request->labours ?? [])->pluck('labour_master_id')->unique();
        $labours = $labourIds->isEmpty() ? collect() : LabourMaster::whereIn('id', $labourIds)->get()->keyBy('id');

        $lines = [];

        foreach ($request->parts ?? [] as $part) {
            $underWarranty = isset($part['under_warranty']) && $part['under_warranty'];
            $lines[] = [
                'line_total' => $underWarranty ? 0 : ($part['quantity'] * ($part['mrp'] + $part['labor_charge'])),
                'gst_rate' => $parts[$part['spare_part_id']]->gst_rate ?? null,
            ];
        }

        foreach ($request->labours ?? [] as $labour) {
            $master = $labours[$labour['labour_master_id']] ?? null;
            $lines[] = [
                'line_total' => $labour['rate'] * $labour['qty'],
                'gst_rate' => ($master && $master->taxable) ? $master->gst_rate : null,
            ];
        }

        return $lines;
    }
}
