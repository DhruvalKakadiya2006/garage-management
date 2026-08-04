<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\SparePart;
use App\Models\LabourMaster;
use App\Models\JobCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\GstCalculator;

class EstimateController extends Controller
{
    public function index()
    {
        $estimates = Estimate::with(['customer', 'vehicle'])->latest()->paginate(15);
        return view('estimates.index', compact('estimates'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $vehicles = Vehicle::with('customer')->orderBy('registration_no')->get();
        $spareParts = SparePart::orderBy('part_name')->get();
        $labourMasters = LabourMaster::orderBy('service_name')->get();
        
        return view('estimates.create', compact('customers', 'vehicles', 'spareParts', 'labourMasters'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'estimate_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'valid_until' => 'nullable|date',
            'status' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.estimate_status'))),
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'gst_applicable' => 'boolean',
            'notes' => 'nullable|string',
            'parts' => 'nullable|array',
            'parts.*.spare_part_id' => 'required|exists:spare_parts,id',
            'parts.*.quantity' => 'required|integer|min:1',
            'parts.*.mrp' => 'required|numeric|min:0',
            'parts.*.labor_charge' => 'required|numeric|min:0',
            'labours' => 'nullable|array',
            'labours.*.labour_master_id' => 'required|exists:labour_masters,id',
            'labours.*.rate' => 'required|numeric|min:0',
            'labours.*.qty' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Calculate totals
            $calculations = app(GstCalculator::class)->calculate(
                $this->buildGstLines($request),
                $request->has('gst_applicable'),
                $request->discount_percent ?? 0,
                $request->discount_amount ?? 0,
            );
            
            $estimate = Estimate::create([
                'estimate_date' => $validated['estimate_date'],
                'customer_id' => $validated['customer_id'],
                'vehicle_id' => $validated['vehicle_id'],
                'valid_until' => $validated['valid_until'] ?? null,
                'status' => $validated['status'],
                'discount_percent' => $calculations['discount_percent'],
                'discount_amount' => $calculations['discount_amount'],
                'gst_applicable' => $request->has('gst_applicable'),
                'cgst_amount' => $calculations['cgst_amount'],
                'sgst_amount' => $calculations['sgst_amount'],
                'round_off_amount' => $calculations['round_off_amount'],
                'grand_total' => $calculations['grand_total'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Save parts
            if (!empty($request->parts)) {
                foreach ($request->parts as $part) {
                    $lineTotal = $part['quantity'] * ($part['mrp'] + $part['labor_charge']);
                    $estimate->parts()->create([
                        'spare_part_id' => $part['spare_part_id'],
                        'quantity' => $part['quantity'],
                        'mrp' => $part['mrp'],
                        'labor_charge' => $part['labor_charge'],
                        'line_total' => $lineTotal,
                    ]);
                }
            }

            // Save labours
            if (!empty($request->labours)) {
                foreach ($request->labours as $labour) {
                    $lineTotal = $labour['rate'] * $labour['qty'];
                    $estimate->labours()->create([
                        'labour_master_id' => $labour['labour_master_id'],
                        'rate' => $labour['rate'],
                        'qty' => $labour['qty'],
                        'line_total' => $lineTotal,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('estimates.show', $estimate)->with('success', 'Estimate created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to create estimate: ' . $e->getMessage()]);
        }
    }

    public function show(Estimate $estimate)
    {
        $estimate->load(['customer', 'vehicle', 'parts.sparePart', 'labours.labourMaster', 'jobCard']);
        return view('estimates.show', compact('estimate'));
    }

    public function edit(Estimate $estimate)
    {
        $estimate->load(['parts', 'labours']);
        $customers = Customer::orderBy('name')->get();
        $vehicles = Vehicle::with('customer')->orderBy('registration_no')->get();
        $spareParts = SparePart::orderBy('part_name')->get();
        $labourMasters = LabourMaster::orderBy('service_name')->get();
        
        return view('estimates.edit', compact('estimate', 'customers', 'vehicles', 'spareParts', 'labourMasters'));
    }

    public function update(Request $request, Estimate $estimate)
    {
        $validated = $request->validate([
            'estimate_date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'valid_until' => 'nullable|date',
            'status' => 'required|in:' . implode(',', array_keys(config('garage.option_lists.estimate_status'))),
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'gst_applicable' => 'boolean',
            'notes' => 'nullable|string',
            'parts' => 'nullable|array',
            'parts.*.spare_part_id' => 'required|exists:spare_parts,id',
            'parts.*.quantity' => 'required|integer|min:1',
            'parts.*.mrp' => 'required|numeric|min:0',
            'parts.*.labor_charge' => 'required|numeric|min:0',
            'labours' => 'nullable|array',
            'labours.*.labour_master_id' => 'required|exists:labour_masters,id',
            'labours.*.rate' => 'required|numeric|min:0',
            'labours.*.qty' => 'required|numeric|min:0',
        ]);

        if (empty($request->parts) && empty($request->labours)) {
            return back()->withInput()->withErrors(['parts' => 'An estimate must have at least one part or labour line.']);
        }

        DB::beginTransaction();
        try {
            // Calculate totals
            $calculations = app(GstCalculator::class)->calculate(
                $this->buildGstLines($request),
                $request->has('gst_applicable'),
                $request->discount_percent ?? 0,
                $request->discount_amount ?? 0,
            );
            
            $estimate->update([
                'estimate_date' => $validated['estimate_date'],
                'customer_id' => $validated['customer_id'],
                'vehicle_id' => $validated['vehicle_id'],
                'valid_until' => $validated['valid_until'] ?? null,
                'status' => $validated['status'],
                'discount_percent' => $calculations['discount_percent'],
                'discount_amount' => $calculations['discount_amount'],
                'gst_applicable' => $request->has('gst_applicable'),
                'cgst_amount' => $calculations['cgst_amount'],
                'sgst_amount' => $calculations['sgst_amount'],
                'round_off_amount' => $calculations['round_off_amount'],
                'grand_total' => $calculations['grand_total'],
                'notes' => $validated['notes'] ?? null,
            ]);

            // Delete existing parts and labours, then recreate
            $estimate->parts()->delete();
            $estimate->labours()->delete();

            // Save parts
            if (!empty($request->parts)) {
                foreach ($request->parts as $part) {
                    $lineTotal = $part['quantity'] * ($part['mrp'] + $part['labor_charge']);
                    $estimate->parts()->create([
                        'spare_part_id' => $part['spare_part_id'],
                        'quantity' => $part['quantity'],
                        'mrp' => $part['mrp'],
                        'labor_charge' => $part['labor_charge'],
                        'line_total' => $lineTotal,
                    ]);
                }
            }

            // Save labours
            if (!empty($request->labours)) {
                foreach ($request->labours as $labour) {
                    $lineTotal = $labour['rate'] * $labour['qty'];
                    $estimate->labours()->create([
                        'labour_master_id' => $labour['labour_master_id'],
                        'rate' => $labour['rate'],
                        'qty' => $labour['qty'],
                        'line_total' => $lineTotal,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('estimates.show', $estimate)->with('success', 'Estimate updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to update estimate: ' . $e->getMessage()]);
        }
    }

    public function destroy(Estimate $estimate)
    {
        $estimate->delete();
        return redirect()->route('estimates.index')->with('success', 'Estimate deleted.');
    }

    public function convertToJobCard(Estimate $estimate)
    {
        if ($estimate->status !== 'approved') {
            return back()->with('error', 'Only approved estimates can be converted to a job card.');
        }

        if ($estimate->job_card_id) {
            return back()->with('error', 'This estimate has already been converted to a job card.');
        }

        $estimate->load(['parts.sparePart', 'labours.labourMaster']);

        // Pre-flight stock check for every part line before any writes, so a
        // short part never produces a partial Job Card.
        $stockErrors = $this->validateConvertStock($estimate);
        if (!empty($stockErrors)) {
            return back()->with('error', implode(' ', $stockErrors));
        }

        DB::beginTransaction();
        try {
            $lines = [];
            foreach ($estimate->parts as $part) {
                $lines[] = [
                    'line_total' => $part->quantity * ($part->mrp + $part->labor_charge),
                    'gst_rate' => $part->sparePart?->gst_rate,
                ];
            }
            foreach ($estimate->labours as $labour) {
                $lines[] = [
                    'line_total' => $labour->rate * $labour->qty,
                    'gst_rate' => ($labour->labourMaster && $labour->labourMaster->taxable) ? $labour->labourMaster->gst_rate : null,
                ];
            }

            $calculations = app(GstCalculator::class)->calculate(
                $lines,
                $estimate->gst_applicable,
                $estimate->discount_percent,
                $estimate->discount_amount,
            );

            $jobCard = JobCard::create([
                'job_date' => now()->toDateString(),
                'customer_id' => $estimate->customer_id,
                'vehicle_id' => $estimate->vehicle_id,
                'job_type' => 'paid_service',
                'status' => 'received',
                'discount_percent' => $estimate->discount_percent,
                'discount_amount' => $estimate->discount_amount,
                'gst_applicable' => $estimate->gst_applicable,
                'cgst_amount' => $calculations['cgst_amount'],
                'sgst_amount' => $calculations['sgst_amount'],
                'round_off_amount' => $calculations['round_off_amount'],
                'grand_total' => $calculations['grand_total'],
                'source_estimate_id' => $estimate->id,
            ]);

            // Copy parts through the JobCardPart model so the boot hooks run
            // (stock validation + deduction) exactly like a normal Job Card.
            foreach ($estimate->parts as $part) {
                $jobCard->parts()->create([
                    'spare_part_id' => $part->spare_part_id,
                    'quantity' => $part->quantity,
                    'mrp' => $part->mrp,
                    'labor_charge' => $part->labor_charge,
                    'under_warranty' => false,
                    'line_total' => $part->quantity * ($part->mrp + $part->labor_charge),
                ]);
            }

            // Copy labours
            foreach ($estimate->labours as $labour) {
                $jobCard->labours()->create([
                    'labour_master_id' => $labour->labour_master_id,
                    'technician_id' => null,
                    'rate' => $labour->rate,
                    'qty' => $labour->qty,
                    'line_total' => $labour->rate * $labour->qty,
                ]);
            }

            $estimate->update(['job_card_id' => $jobCard->id]);

            DB::commit();
            return redirect()->route('job-cards.show', $jobCard)
                ->with('success', 'Estimate ' . $estimate->estimate_no . ' converted to ' . $jobCard->job_card_no . '.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to convert estimate to job card: ' . $e->getMessage());
        }
    }

    private function validateConvertStock(Estimate $estimate): array
    {
        $errors = [];

        // Aggregate requested quantity per part across all estimate lines
        $usageByPart = [];
        foreach ($estimate->parts as $part) {
            $partId = (int) $part->spare_part_id;
            $usageByPart[$partId] = ($usageByPart[$partId] ?? 0) + (int) $part->quantity;
        }

        foreach ($usageByPart as $partId => $requestedQty) {
            $sparePart = SparePart::find($partId);
            if (!$sparePart) {
                continue;
            }
            $available = (int) $sparePart->current_stock_qty;
            if ($requestedQty > $available) {
                $errors[] = "Insufficient stock for '{$sparePart->part_name}'. Requested {$requestedQty}, only {$available} available.";
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
            $lines[] = [
                'line_total' => $part['quantity'] * ($part['mrp'] + $part['labor_charge']),
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
