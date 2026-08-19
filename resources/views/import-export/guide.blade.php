<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="flex items-center gap-2 font-semibold text-xl text-gray-800 leading-tight">
                <x-heroicon-o-book-open class="w-6 h-6 text-primary" />
                Import / Export Migration Guide
            </h2>
            <x-button variant="secondary" :href="route('import-export.index')">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Back
            </x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-gray-600">
                A safe procedure for moving data from an existing system (old software, spreadsheets or another
                database) into this application. Follow the order below — it matches the real dependencies between
                records. An exported file can always be imported back unchanged, so exports double as backups.
            </div>

            <x-card title="Why this order matters">
                <div class="text-sm text-gray-600 space-y-2">
                    <p>Records reference other records, and imports resolve those references by stable identifiers
                        (<code class="text-xs bg-muted px-1.5 py-0.5 rounded">external_ref</code> or a unique business
                        key), never by names alone:</p>
                    <ul class="space-y-1 list-disc list-inside">
                        <li>A <span class="font-medium">Job Card</span> must point at a real <span class="font-medium">Customer</span> and a real <span class="font-medium">Vehicle</span>.</li>
                        <li>A <span class="font-medium">Vehicle</span> must belong to a real <span class="font-medium">Customer</span>.</li>
                        <li>A <span class="font-medium">Job Card</span> may reference <span class="font-medium">Employees</span> as technician/supervisor.</li>
                    </ul>
                    <p class="pt-1">If a reference is missing, that row is reported as an error and skipped — nothing is
                        silently merged or guessed. Fix the reference, or create the parent record first, then re-import.</p>
                </div>
            </x-card>

            <x-card title="Step 1 — Get the structure">
                <div class="text-sm text-gray-600 space-y-2">
                    <p>Download <span class="font-medium">Customers</span> and <span class="font-medium">Job Cards</span>
                        import templates from the Import / Export page (or export your current data to use its exact format).</p>
                    <p>Both the template and the export use identical columns, so a file you export is a file you can import.</p>
                </div>
            </x-card>

            <x-card title="Step 2 — Prepare customer data">
                <div class="text-sm text-gray-600 space-y-2">
                    <p>Fill the customer template. Each row needs a unique <code class="text-xs bg-muted px-1.5 py-0.5 rounded">external_ref</code>
                        (recommended) and a unique <code class="text-xs bg-muted px-1.5 py-0.5 rounded">mobile</code> — these are how existing
                        customers are matched on re-import.</p>
                    <p>Customers are matched by <code class="text-xs bg-muted px-1.5 py-0.5 rounded">external_ref</code> first, then
                        <code class="text-xs bg-muted px-1.5 py-0.5 rounded">mobile</code>. The file is validated and shown as a preview
                        before anything is written.</p>
                </div>
            </x-card>

            <x-card title="Step 3 — Import Customers">
                <div class="text-sm text-gray-600 space-y-2">
                    <p>Upload the customer CSV on the Import / Export page, review the preview, then confirm.
                        Choose <span class="font-medium">Create only</span> for a first load, or
                        <span class="font-medium">Create or update</span> when re-importing an updated export.</p>
                    <p>Customers are not linked to their vehicles by import — vehicles stay managed from the Customers page.</p>
                </div>
            </x-card>

            <x-card title="Step 4 — Ensure required Vehicles exist">
                <div class="text-sm text-gray-600 space-y-2">
                    <p>Job Cards reference vehicles by <code class="text-xs bg-muted px-1.5 py-0.5 rounded">vehicle_external_id</code>
                        (recommended) or <code class="text-xs bg-muted px-1.5 py-0.5 rounded">vehicle_registration_no</code>.
                        Vehicles are not imported on their own — create them from each customer's page (Add Vehicle), or in the
                        Customer list under the customer's Vehicles.</p>
                    <p>Give each vehicle an <code class="text-xs bg-muted px-1.5 py-0.5 rounded">external_ref</code> in its form so
                        Job Card imports can resolve it reliably. Every vehicle in your Job Card file must exist before the Job Card import.</p>
                </div>
            </x-card>

            <x-card title="Step 5 — Prepare Job Card data">
                <div class="text-sm text-gray-600 space-y-2">
                    <p>Fill the Job Card template. Required columns:
                        <code class="text-xs bg-muted px-1.5 py-0.5 rounded">job_date</code>,
                        <code class="text-xs bg-muted px-1.5 py-0.5 rounded">job_type</code>,
                        <code class="text-xs bg-muted px-1.5 py-0.5 rounded">status</code> plus a
                        customer reference and a vehicle reference.</p>
                    <p>Job Cards are matched by <code class="text-xs bg-muted px-1.5 py-0.5 rounded">external_ref</code> first, then
                        <code class="text-xs bg-muted px-1.5 py-0.5 rounded">job_card_no</code>. Leave
                        <code class="text-xs bg-muted px-1.5 py-0.5 rounded">grand_total</code> blank for an un-billed card — it stays
                        NULL so the card still shows as <em>unbilled</em>.</p>
                </div>
            </x-card>

            <x-card title="Step 6 — Import Job Cards">
                <div class="text-sm text-gray-600 space-y-2">
                    <p>Upload the Job Card CSV and review the preview. The preview shows the resolved customer, vehicle and
                        technician for each row, the planned action (Create / Update / Skip) and any warnings or errors.</p>
                    <p>Rows whose customer/vehicle cannot be resolved, or whose vehicle belongs to a different customer, are
                        skipped with a clear message and listed in the downloadable error report.</p>
                </div>
            </x-card>

            <x-card title="Step 7 — Review errors and warnings">
                <div class="text-sm text-gray-600 space-y-2">
                    <p>After importing, the result page shows totals (created / updated / skipped / errors / warnings) and a
                        downloadable CSV report listing every issue. Fix the reported rows and re-import the same file —
                        matching records are updated, so nothing is duplicated.</p>
                </div>
            </x-card>

            <x-card title="Step 8 — Verify totals and historical records">
                <div class="text-sm text-gray-600 space-y-2">
                    <p>Confirm the numbers add up: same number of Customers, Vehicles and Job Cards as the source; Job Cards
                        pointing at the right customer, vehicle and technician; historical values (dates, status, totals,
                        created/updated timestamps) preserved.</p>
                    <p>Tip: export everything after importing and compare the export with the source file — a clean
                        <strong>export → import → export</strong> round trip is the strongest proof of a correct migration.</p>
                </div>
            </x-card>

            <x-card title="Data safety">
                <div class="text-sm text-gray-600 space-y-2">
                    <ul class="space-y-1 list-disc list-inside">
                        <li>Imports require a logged-in user; every import/export action is behind authentication.</li>
                        <li>Nothing is written during a preview. Confirmation is always a separate, explicit step.</li>
                        <li>Rows with errors are never applied, and the whole import runs in one transaction — a database
                            failure rolls everything back.</li>
                        <li>Duplicate records inside a file, or ambiguous references, are reported — never silently created
                            or merged.</li>
                        <li>Uploaded files are stored privately and deleted after a successful import.</li>
                    </ul>
                </div>
            </x-card>

        </div>
    </div>
</x-app-layout>