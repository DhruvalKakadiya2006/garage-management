<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesSearchFilterSort;
use App\Http\Controllers\Concerns\BulkDeletesRecords;
use App\Http\Controllers\Concerns\BulkExportsRecords;
use App\Http\Requests\FilterCustomersRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Models\CounterInvoice;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    use AppliesSearchFilterSort;
    use BulkDeletesRecords;
    use BulkExportsRecords;

    protected function bulkDeleteModel(): string
    {
        return Customer::class;
    }

    protected function bulkExportEntity(): string
    {
        return 'customers';
    }

    public function index(FilterCustomersRequest $request)
    {
        $validated = $request->validated();

        // Full-collection loads (chipping every job card / invoice row into
        // PHP just to sum balances) are replaced with database-side aggregates:
        // withSum for billed amounts, addSelect subqueries for paid amounts.
        $query = Customer::query()
            ->with(['vehicles.vehicleModel.vehicleBrand'])
            ->withCount(['jobCards', 'counterInvoices'])
            ->withSum('jobCards as job_billed', 'grand_total')
            ->withSum('counterInvoices as invoice_billed', 'grand_total')
            ->addSelect([
                'job_paid' => DB::table('payments')
                    ->join('job_cards', 'payments.payable_id', '=', 'job_cards.id')
                    ->where('payments.payable_type', JobCard::class)
                    ->whereColumn('job_cards.customer_id', 'customers.id')
                    ->whereNull('payments.deleted_at')
                    ->selectRaw('COALESCE(SUM(payments.amount), 0)'),
                'invoice_paid' => DB::table('payments')
                    ->join('counter_invoices', 'payments.payable_id', '=', 'counter_invoices.id')
                    ->where('payments.payable_type', CounterInvoice::class)
                    ->whereColumn('counter_invoices.customer_id', 'customers.id')
                    ->whereNull('payments.deleted_at')
                    ->selectRaw('COALESCE(SUM(payments.amount), 0)'),
            ]);

        // Apply search
        if ($request->filled('search')) {
            $query = $this->applySearch(
                $query,
                $validated['search'],
                ['display_name', 'mobile']
            );
        }

        // Apply outstanding balance filter
        if ($request->filled('outstanding') && $validated['outstanding'] !== 'all') {
            if ($validated['outstanding'] === 'has_outstanding') {
                $query->havingRaw('(COALESCE(job_billed, 0) + COALESCE(invoice_billed, 0)) > (COALESCE(job_paid, 0) + COALESCE(invoice_paid, 0))');
            } elseif ($validated['outstanding'] === 'no_outstanding') {
                $query->havingRaw('(COALESCE(job_billed, 0) + COALESCE(invoice_billed, 0)) <= (COALESCE(job_paid, 0) + COALESCE(invoice_paid, 0))');
            }
        }

        // Apply sorting
        $sortColumn = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';

        if ($sortColumn === 'total_visits') {
            // Sort by computed total visits (job_cards_count + counter_invoices_count)
            $query->orderByRaw('(job_cards_count + counter_invoices_count) '.$sortDirection);
        } elseif ($sortColumn === 'outstanding') {
            // Sort by computed outstanding balance
            $query->orderByRaw('((COALESCE(job_billed, 0) + COALESCE(invoice_billed, 0)) - (COALESCE(job_paid, 0) + COALESCE(invoice_paid, 0))) '.$sortDirection);
        } else {
            $query = $this->applySort(
                $query,
                $sortColumn,
                $sortDirection,
                ['display_name', 'created_at'],
                ['column' => 'created_at', 'direction' => 'desc']
            );
        }

        $perPage = $this->getPerPage($request);
        $customers = $query->paginate($perPage)->withQueryString();

        $customers->getCollection()->transform(function (Customer $customer) {
            $customer->setAttribute(
                'outstanding',
                max(0, (float) $customer->job_billed + (float) $customer->invoice_billed
                    - (float) $customer->job_paid - (float) $customer->invoice_paid)
            );

            return $customer;
        });

        return view('customers.index', compact('customers'));
    }

    /**
     * Search customers by name or mobile for the searchable combobox. Only a
     * small bounded result set is returned; empty queries return nothing so
     * this endpoint never dumps the full customer table into the browser.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q'));

        if ($q === '') {
            return response()->json([]);
        }

        // Escape LIKE wildcards (with an explicit ESCAPE clause) so a query
        // like "50%" matches that literal name only.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q);

        $customers = Customer::query()
            ->withCount('vehicles')
            ->where(function ($query) use ($escaped) {
                $query->whereRaw("display_name LIKE ? ESCAPE '\'", ["%{$escaped}%"])
                    ->orWhereRaw("mobile LIKE ? ESCAPE '\'", ["%{$escaped}%"]);
            })
            ->orderBy('display_name')
            ->limit(10)
            ->get(['id', 'display_name', 'mobile']);

        return response()->json($customers->map(fn ($c) => [
            'id' => (string) $c->id,
            'display_name' => $c->display_name,
            'mobile' => $c->mobile,
            'vehicles_count' => $c->vehicles_count,
            'label' => $c->display_name.' — '.$c->mobile,
        ]));
    }

    /**
     * Return only the selected customer's vehicles for the Job Card form so a
     * page never embeds every vehicle in the database.
     */
    public function vehicles(Customer $customer)
    {
        $vehicles = $customer->vehicles()
            ->with(['vehicleModel.vehicleBrand'])
            ->orderBy('registration_no')
            ->get();

        return response()->json($vehicles->map(fn ($v) => [
            'id' => (string) $v->id,
            'customer_id' => (string) $v->customer_id,
            'registration_no' => $v->registration_no,
            'is_non_rto' => (bool) $v->is_non_rto,
            'brand' => $v->vehicleModel?->vehicleBrand?->name,
            'model' => $v->vehicleModel?->name,
            'label' => trim(($v->registration_no ?? 'NON-RTO').' — '.($v->vehicleModel?->vehicleBrand?->name ?? '').' '.($v->vehicleModel?->name ?? '')),
        ]));
    }

    public function show(Request $request, Customer $customer)
    {
        $tab = $request->query('tab', 'vehicles');
        if (! in_array($tab, ['vehicles', 'job_cards', 'financial'], true)) {
            $tab = 'vehicles';
        }

        $customer->loadCount(['vehicles', 'jobCards', 'counterInvoices']);
        $customer->load('vehicles.vehicleModel.vehicleBrand');

        // Balances are derived entirely from existing transactions via SQL
        // aggregates — no customer columns and no full-collection loads.
        $jobBilled = (float) $customer->jobCards()->sum('grand_total');
        $invoiceBilled = (float) $customer->counterInvoices()->sum('grand_total');

        $jobPaid = (float) DB::table('payments')
            ->join('job_cards', 'payments.payable_id', '=', 'job_cards.id')
            ->where('payments.payable_type', JobCard::class)
            ->where('job_cards.customer_id', $customer->id)
            ->whereNull('payments.deleted_at')
            ->sum('payments.amount');

        $invoicePaid = (float) DB::table('payments')
            ->join('counter_invoices', 'payments.payable_id', '=', 'counter_invoices.id')
            ->where('payments.payable_type', CounterInvoice::class)
            ->where('counter_invoices.customer_id', $customer->id)
            ->whereNull('payments.deleted_at')
            ->sum('payments.amount');

        $totalPaid = $jobPaid + $invoicePaid;
        $totalBilled = $jobBilled + $invoiceBilled;

        $customer->setAttribute('billed', $totalBilled);
        $customer->setAttribute('paid', $totalPaid);
        $customer->setAttribute('outstanding', max(0, $totalBilled - $totalPaid));

        // Constrained, paginated tab data (latest first).
        $jobCards = $customer->jobCards()
            ->with(['vehicle.vehicleModel.vehicleBrand'])
            ->withSum('payments', 'amount')
            ->orderByDesc('job_date')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'job_page')
            ->withQueryString();

        $counterInvoices = $customer->counterInvoices()
            ->withSum('payments', 'amount')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(8, ['*'], 'invoice_page')
            ->withQueryString();

        $payments = Payment::query()
            ->whereHasMorph('payable', [JobCard::class, CounterInvoice::class], fn ($q) => $q->where('customer_id', $customer->id))
            ->with('payable')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'payment_page')
            ->withQueryString();

        // Outstanding documents drive the "Receive Payment" fast action.
        $outstandingJobCards = $customer->jobCards()
            ->select('id', 'job_card_no', 'job_date', 'grand_total')
            ->withSum('payments', 'amount')
            ->whereRaw('COALESCE(grand_total, 0) > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = job_cards.id AND payments.payable_type = ? AND payments.deleted_at IS NULL)', [JobCard::class])
            ->orderByDesc('job_date')
            ->get();

        $outstandingInvoices = $customer->counterInvoices()
            ->select('id', 'invoice_no', 'invoice_date', 'grand_total')
            ->withSum('payments', 'amount')
            ->whereRaw('COALESCE(grand_total, 0) > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.payable_id = counter_invoices.id AND payments.payable_type = ? AND payments.deleted_at IS NULL)', [CounterInvoice::class])
            ->orderByDesc('invoice_date')
            ->get();

        $outstandingDocuments = $outstandingJobCards
            ->map(fn ($jc) => [
                'id' => (string) $jc->id,
                'type' => JobCard::class,
                'label' => $jc->job_card_no,
                'date' => $jc->job_date->format('d M Y'),
                'outstanding' => round((float) $jc->outstanding, 2),
            ])
            ->merge($outstandingInvoices->map(fn ($ci) => [
                'id' => (string) $ci->id,
                'type' => CounterInvoice::class,
                'label' => $ci->invoice_no,
                'date' => $ci->invoice_date->format('d M Y'),
                'outstanding' => round((float) $ci->outstanding, 2),
            ]))
            ->sortByDesc('date')
            ->values();

        $createJobCardParams = ['customer_id' => $customer->id];
        if ($customer->vehicles_count === 1) {
            $createJobCardParams['vehicle_id'] = $customer->vehicles->first()->id;
        }

        return view('customers.show', compact('customer', 'tab', 'jobCards', 'counterInvoices', 'payments', 'outstandingDocuments', 'createJobCardParams'));
    }

    /**
     * Stream a customer statement PDF built from existing transactional data.
     *
     * Balances are computed with SQL aggregates constrained to the selected
     * date range; no separate ledger or statement tables are introduced.
     */
    public function statement(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $from = isset($validated['from_date'])
            ? Carbon::parse($validated['from_date'])->startOfDay()
            : $this->defaultStatementFrom();
        $to = isset($validated['to_date'])
            ? Carbon::parse($validated['to_date'])->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $data = $this->statementData($customer, $from, $to);

        $filename = 'CUSTOMER-STATEMENT-'.Str::slug($customer->display_name).'.pdf';

        $pdf = Pdf::setBasePath(base_path())
            ->loadView('pdf.customer-statement', $data);

        return $pdf->download($filename);
    }

    /**
     * Build every piece of data the statement PDF needs. Kept as a separate
     * public method so the SQL aggregates are directly testable.
     *
     * @return array<string, mixed>
     */
    public function statementData(Customer $customer, Carbon $from, Carbon $to): array
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $customer->load('vehicles.vehicleModel.vehicleBrand');

        $jobCards = $customer->jobCards()
            ->with(['vehicle.vehicleModel.vehicleBrand'])
            ->withSum(['payments as collected_in_range' => fn ($q) => $q->whereBetween('payment_date', [$fromStr, $toStr])], 'amount')
            ->withSum(['payments as paid_to' => fn ($q) => $q->where('payment_date', '<=', $toStr)], 'amount')
            ->whereBetween('job_date', [$fromStr, $toStr])
            ->orderBy('job_date')
            ->orderBy('id')
            ->get();

        $counterInvoices = $customer->counterInvoices()
            ->withSum(['payments as collected_in_range' => fn ($q) => $q->whereBetween('payment_date', [$fromStr, $toStr])], 'amount')
            ->withSum(['payments as paid_to' => fn ($q) => $q->where('payment_date', '<=', $toStr)], 'amount')
            ->whereBetween('invoice_date', [$fromStr, $toStr])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $payments = Payment::query()
            ->whereHasMorph('payable', [JobCard::class, CounterInvoice::class], fn ($q) => $q->where('customer_id', $customer->id))
            ->with('payable')
            ->whereBetween('payment_date', [$fromStr, $toStr])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $billedInRange = (float) $customer->jobCards()->whereBetween('job_date', [$fromStr, $toStr])->sum('grand_total')
            + (float) $customer->counterInvoices()->whereBetween('invoice_date', [$fromStr, $toStr])->sum('grand_total');

        $collectedInRange = (float) Payment::query()
            ->whereHasMorph('payable', [JobCard::class, CounterInvoice::class], fn ($q) => $q->where('customer_id', $customer->id))
            ->whereBetween('payment_date', [$fromStr, $toStr])
            ->sum('amount');

        $billedAsOf = (float) $customer->jobCards()->where('job_date', '<=', $toStr)->sum('grand_total')
            + (float) $customer->counterInvoices()->where('invoice_date', '<=', $toStr)->sum('grand_total');

        $paidAsOf = (float) Payment::query()
            ->whereHasMorph('payable', [JobCard::class, CounterInvoice::class], fn ($q) => $q->where('customer_id', $customer->id))
            ->where('payment_date', '<=', $toStr)
            ->sum('amount');

        $outstandingAsOf = max(0, $billedAsOf - $paidAsOf);

        return compact('customer', 'from', 'to', 'jobCards', 'counterInvoices', 'payments', 'billedInRange', 'collectedInRange', 'outstandingAsOf');
    }

    private function defaultStatementFrom(): Carbon
    {
        $year = now()->month >= 4 ? now()->year : now()->year - 1;

        return Carbon::create($year, 4, 1)->startOfDay();
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(StoreCustomerRequest $request)
    {
        $validated = $request->validated();

        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', 'Customer added successfully.');
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'surname' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', 'unique:customers,mobile,'.$customer->id],
            'alt_mobile' => ['nullable', 'string', 'regex:/^[6-9]\d{9}$/'],
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('admin');

        try {
            $customer->delete();

            return redirect()->route('customers.index')->with('success', 'Customer deleted.');
        } catch (QueryException $e) {
            return back()->with('error', 'Cannot delete — this customer has existing job cards or invoices linked to it.');
        }
    }
}
