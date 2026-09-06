<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Legacy Data Import — VSMS4700011083 (SQL Server) -> Laravel/SQLite
 *
 * READ THIS FIRST:
 * - This command is idempotent: every imported record gets an `external_ref`
 *   (e.g. "MST_HISTORY:15651"). Re-running is always safe — already-imported
 *   rows are skipped, not duplicated.
 * - ALWAYS run with --dry-run first. It runs every query and every mapping
 *   decision, logs what WOULD happen, and touches zero rows in SQLite.
 * - Import order matters (foreign keys): vehicle-catalog -> employees ->
 *   customers -> vehicles -> spare-parts -> labour-master -> job-cards.
 *   Running `php artisan legacy:import all` does this in the right order
 *   automatically. Running a single step out of order will fail loudly
 *   (missing FK), not silently corrupt anything.
 * - Business-logic model events (stock decrement, auto job-card numbering,
 *   GST recalculation) are intentionally bypassed here via direct DB::table()
 *   inserts — this is HISTORICAL data, not a live transaction. Don't remove
 *   this bypass.
 * - A few mappings are marked [VERIFY] below — these are best-effort guesses
 *   based on column names / limited sample data, not confirmed against a
 *   full data review. Check the --dry-run summary output for these before
 *   trusting a real run.
 *
 * USAGE:
 *   php artisan legacy:import all --dry-run
 *   php artisan legacy:import all --dry-run --limit=50
 *   php artisan legacy:import vehicle-catalog
 *   php artisan legacy:import all
 */
class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import
        {entity=all : all|vehicle-catalog|employees|customers|vehicles|spare-parts|labour-master|job-cards}
        {--dry-run : Preview only, write nothing}
        {--limit= : Limit rows processed per entity, for testing}';

    protected $description = 'Import historical data from the legacy VSMS SQL Server database';

    protected bool $dryRun = false;

    protected ?int $limit = null;

    /** @var array<string,int> mobile (normalized) => new customer id */
    protected array $customerByMobile = [];

    /** Known EV brand prefixes for splitting PRODUCT_NAME when MFG_CO_CODE
     *  isn't reliable (sample data showed the same MFG_CO_CODE=0000099 on
     *  both an Okinawa and an Ola product — don't trust that column alone). */
    protected array $knownBrands = [
        'OLA', 'ATHER', 'TVS', 'BAJAJ', 'HERO', 'AMPERE', 'OKINAWA',
        'REVOLT', 'PURE EV', 'BATTRE', 'CHETAK',
    ];

    protected array $summary = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $limitOpt = $this->option('limit');
        $this->limit = $limitOpt !== null ? (int) $limitOpt : null;

        if (! $this->confirmLegacyConnection()) {
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('DRY RUN — no data will be written to SQLite.');
        }

        $entity = $this->argument('entity');
        $steps = [
            'vehicle-catalog' => 'importVehicleCatalog',
            'employees' => 'importEmployees',
            'customers' => 'importCustomers',
            'vehicles' => 'importVehicles',
            'spare-parts' => 'importSpareParts',
            'labour-master' => 'importLabourMaster',
            'job-cards' => 'importJobCards',
        ];

        if ($entity === 'all') {
            foreach ($steps as $label => $method) {
                $this->newLine();
                $this->info("=== {$label} ===");
                $this->{$method}();
            }
        } elseif (isset($steps[$entity])) {
            $this->{$steps[$entity]}();
        } else {
            $this->error("Unknown entity '{$entity}'. Valid: all, ".implode(', ', array_keys($steps)));

            return self::FAILURE;
        }

        $this->printSummary();

        return self::SUCCESS;
    }

    protected function confirmLegacyConnection(): bool
    {
        try {
            DB::connection('legacy')->select('SELECT 1 AS ok');

            return true;
        } catch (\Throwable $e) {
            $this->error('Could not connect to the legacy SQL Server database.');
            $this->line('Check the "legacy" connection in config/database.php and your .env — see setup notes.');
            $this->line($e->getMessage());

            return false;
        }
    }

    protected function legacy()
    {
        return DB::connection('legacy')->table(...func_get_args());
    }

    // ─────────────────────────────────────────────────────────────
    // Shared helpers
    // ─────────────────────────────────────────────────────────────

    /** Legacy dates are 8-char 'YYYYMMDD' strings, but genuinely malformed
     *  in places (blank, wrong length, non-numeric) — parse defensively. */
    protected function parseLegacyDate(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || strlen($raw) !== 8 || ! ctype_digit($raw)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Ymd', $raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Times seen in sample data include garbage like "  :" — only accept
     *  a clean H:i / H:i:s pattern, otherwise null rather than guessing. */
    protected function parseLegacyTime(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $raw, $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }

        return null;
    }

    protected function normalizeMobile(?string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);

        return (strlen($digits) >= 10) ? substr($digits, -10) : null;
    }

    protected function record(string $bucket, string $type, ?string $ref = null, ?string $detail = null): void
    {
        $this->summary[$bucket][$type] = ($this->summary[$bucket][$type] ?? 0) + 1;
        if ($detail) {
            Log::channel('single')->info("[legacy:import] {$bucket}/{$type}".($ref ? " ({$ref})" : '').": {$detail}");
        }
    }

    protected function printSummary(): void
    {
        $this->newLine();
        $this->info('=== Import Summary ===');
        foreach ($this->summary as $bucket => $types) {
            $this->line("<comment>{$bucket}</comment>");
            foreach ($types as $type => $count) {
                $this->line("  {$type}: {$count}");
            }
        }
        $this->newLine();
        $this->line('Check storage/logs/laravel.log for per-row skip/error details.');
    }

    protected function alreadyImported(string $table, string $externalRef): ?int
    {
        if ($this->dryRun) {
            // In dry-run, nothing is actually written to SQLite, so we track
            // "would have been created" in memory instead — otherwise every
            // downstream step (vehicles needing a customer, job cards needing
            // a vehicle) would wrongly report everything as missing.
            return $this->dryRunRefs[$table][$externalRef] ?? null;
        }
        $row = DB::table($table)->where('external_ref', $externalRef)->first(['id']);

        return $row?->id;
    }

    /** @var array<string,array<string,int>> table => external_ref => fake id */
    protected array $dryRunRefs = [];

    /** Call this at every "would create" point (both dry-run and real),
     *  so later steps in the same run — dry or real — can resolve the link.
     *  $realId is ignored in dry-run mode; a stable negative counter is used
     *  instead so every dry-run "id" is still unique across the whole run. */
    protected int $dryRunIdCounter = -1;

    protected function markCreated(string $table, string $externalRef, ?int $realId): int
    {
        if ($this->dryRun) {
            $id = $this->dryRunIdCounter--;
            $this->dryRunRefs[$table][$externalRef] = $id;

            return $id;
        }
        $this->dryRunRefs[$table][$externalRef] = $realId; // harmless to also track in real runs

        return $realId;
    }

    // ─────────────────────────────────────────────────────────────
    // 1. Vehicle Catalog (MST_PRODUCT + MST_MFG_COMPANY)
    // ─────────────────────────────────────────────────────────────

    protected function importVehicleCatalog(): void
    {
        $query = $this->legacy('MST_PRODUCT')->select('*');
        if ($this->limit) {
            $query->limit($this->limit);
        }
        $products = $query->get();

        $bar = $this->output->createProgressBar($products->count());
        $brandCache = []; // brand name (upper) => new vehicle_brands.id

        foreach ($products as $p) {
            $bar->advance();

            $productName = trim((string) $p->PRODUCT_NAME);
            if ($productName === '') {
                $this->record('vehicle-catalog', 'skipped_blank_name');

                continue;
            }

            // [VERIFY] MFG_CO_CODE was inconsistent in sample data (same code
            // on an Okinawa and an Ola product). Resolve brand primarily by
            // matching a known-brand prefix in PRODUCT_NAME; fall back to
            // MFG_CO_CODE's company name only if no known brand matches;
            // final fallback is a generic "Other" brand.
            $brandName = null;
            foreach ($this->knownBrands as $known) {
                if (stripos($productName, $known) === 0) {
                    $brandName = ucwords(strtolower($known));
                    break;
                }
            }
            $modelName = $brandName
                ? trim(substr($productName, strlen($brandName)))
                : $productName;

            if (! $brandName) {
                $brandName = 'Other';
                $modelName = $productName;
                $this->record('vehicle-catalog', 'brand_unresolved_flagged', $p->PRODUCT_CODE, $productName);
            }
            if ($modelName === '') {
                $modelName = 'Base';
            }

            $externalRefBrand = 'MST_MFG_COMPANY:name:'.strtoupper($brandName);
            $externalRefModel = 'MST_PRODUCT:'.$p->PRODUCT_CODE;

            if (isset($brandCache[$brandName])) {
                $brandId = $brandCache[$brandName];
            } else {
                $existing = $this->alreadyImported('vehicle_brands', $externalRefBrand)
                    ?? DB::table('vehicle_brands')->where('name', $brandName)->value('id');
                if ($existing) {
                    $brandId = $existing;
                } else {
                    $realId = $this->dryRun ? null : DB::table('vehicle_brands')->insertGetId([
                        'name' => $brandName,
                        'external_ref' => $externalRefBrand,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $brandId = $this->markCreated('vehicle_brands', $externalRefBrand, $realId);
                    $this->record('vehicle-catalog', 'brands_created');
                }
                $brandCache[$brandName] = $brandId;
            }

            if ($this->alreadyImported('vehicle_models', $externalRefModel)) {
                $this->record('vehicle-catalog', 'models_skipped_existing');

                continue;
            }

            // A different legacy PRODUCT_CODE can resolve to the exact same
            // (brand, model name) pair — e.g. two catalog entries that both
            // end up as "Ola S1 X" after stripping. Reuse the existing
            // vehicle_models row rather than trying to insert a duplicate
            // (the DB's own unique constraint on brand+name would reject it).
            $existingModel = $this->dryRun
                ? ($this->dryRunRefs['vehicle_models_by_brand_name'][$brandId.'|'.$modelName] ?? null)
                : DB::table('vehicle_models')->where('vehicle_brand_id', $brandId)->where('name', $modelName)->value('id');

            if ($existingModel) {
                $this->markCreated('vehicle_models', $externalRefModel, $existingModel);
                $this->record('vehicle-catalog', 'models_deduped_same_brand_name');

                continue;
            }

            $realModelId = $this->dryRun ? null : DB::table('vehicle_models')->insertGetId([
                'vehicle_brand_id' => $brandId,
                'name' => $modelName,
                'battery_type' => stripos((string) $p->FUELTYPE, 'BATTERY') !== false ? 'lithium-ion' : null,
                'external_ref' => $externalRefModel,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $newModelId = $this->markCreated('vehicle_models', $externalRefModel, $realModelId);
            $this->dryRunRefs['vehicle_models_by_brand_name'][$brandId.'|'.$modelName] = $newModelId;
            $this->record('vehicle-catalog', 'models_created');
        }
        $bar->finish();
        $this->newLine();
    }

    // ─────────────────────────────────────────────────────────────
    // 2. Employees (MST_EMP) — skip placeholder rows (NONE/SELF)
    // ─────────────────────────────────────────────────────────────

    protected function importEmployees(): void
    {
        $query = $this->legacy('MST_EMP')->select('*')
            ->whereNotIn('EMPLOYER_NAME', ['NONE', 'SELF', '']);
        if ($this->limit) {
            $query->limit($this->limit);
        }
        $rows = $query->get();

        $bar = $this->output->createProgressBar($rows->count());
        foreach ($rows as $e) {
            $bar->advance();
            $externalRef = 'MST_EMP:'.$e->EMPLOYER_CODE;
            if ($this->alreadyImported('employees', $externalRef)) {
                $this->record('employees', 'skipped_existing');

                continue;
            }

            $name = trim((string) $e->EMPLOYER_NAME);
            if ($name === '') {
                $this->record('employees', 'skipped_blank_name');

                continue;
            }

            $realId = $this->dryRun ? null : DB::table('employees')->insertGetId([
                'name' => $name,
                // [VERIFY] legacy has no clean role field we found —
                // defaulting everyone to 'technician'; correct manually
                // in the app afterward for supervisors/helpers/front-desk.
                'role' => 'technician',
                'mobile' => $this->normalizeMobile($e->MOBILE) ?? '0000000000',
                'active' => ! ((bool) ($e->DEACTIVE ?? false)),
                'external_ref' => $externalRef,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->markCreated('employees', $externalRef, $realId);
            $this->record('employees', 'created');
        }
        $bar->finish();
        $this->newLine();
        $this->warn('Role defaulted to "technician" for all imported employees — review and correct roles (supervisor/helper/front_desk) manually in the app.');
    }

    // ─────────────────────────────────────────────────────────────
    // 3. Customers (MST_HISTORY, deduplicated by mobile)
    // ─────────────────────────────────────────────────────────────

    protected function importCustomers(): void
    {
        $query = $this->legacy('MST_HISTORY')->select('*')->orderBy('HIST_ID');
        if ($this->limit) {
            $query->limit($this->limit);
        }
        $rows = $query->get();

        $bar = $this->output->createProgressBar($rows->count());
        $seenMobiles = [];

        foreach ($rows as $h) {
            $bar->advance();

            $mobile = $this->normalizeMobile($h->MOBILE1);
            $name = trim((string) $h->CUSTOMER_NAME) ?: 'Unknown Customer';

            if ($mobile === null) {
                // No usable mobile — still import as its own customer using
                // HIST_CODE as a synthetic unique key, flagged for review,
                // rather than silently dropping the record.
                $externalRef = 'MST_HISTORY:nomobile:'.$h->HIST_CODE;
                $mobileValue = 'NOMOB-'.$h->HIST_CODE; // placeholder, unique
                $this->record('customers', 'flagged_no_mobile', $h->HIST_CODE, $name);
            } else {
                if (isset($seenMobiles[$mobile])) {
                    // Already created this customer from an earlier row —
                    // nothing to do, the vehicle-import step links to them.
                    $this->record('customers', 'deduped_existing_mobile');

                    continue;
                }
                $externalRef = 'MST_HISTORY:mobile:'.$mobile;
                $mobileValue = $mobile;
            }

            if ($this->alreadyImported('customers', $externalRef)) {
                if ($mobile !== null) {
                    $seenMobiles[$mobile] = $this->alreadyImported('customers', $externalRef);
                }
                $this->record('customers', 'skipped_existing');

                continue;
            }

            $address = trim(implode(', ', array_filter([$h->ADDRESS1, $h->ADDRESS2])));

            $realId = $this->dryRun ? null : DB::table('customers')->insertGetId([
                'mobile' => $mobileValue,
                'alt_mobile' => $this->normalizeMobile($h->MOBILE2),
                'email' => trim((string) $h->EMAIL) ?: null,
                'address' => $address ?: null,
                'first_name' => $name,
                'display_name' => $name,
                'external_ref' => $externalRef,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $id = $this->markCreated('customers', $externalRef, $realId);

            if ($mobile !== null) {
                $seenMobiles[$mobile] = $id;
            }
            $this->record('customers', 'created');
        }
        $bar->finish();
        $this->newLine();
    }

    // ─────────────────────────────────────────────────────────────
    // 4. Vehicles (MST_HISTORY, one row per vehicle, linked to customer)
    // ─────────────────────────────────────────────────────────────

    protected function importVehicles(): void
    {
        $query = $this->legacy('MST_HISTORY')->select('*')->orderBy('HIST_ID');
        if ($this->limit) {
            $query->limit($this->limit);
        }
        $rows = $query->get();

        $bar = $this->output->createProgressBar($rows->count());
        foreach ($rows as $h) {
            $bar->advance();

            $externalRef = 'MST_HISTORY:vehicle:'.$h->HIST_CODE;
            if ($this->alreadyImported('vehicles', $externalRef)) {
                $this->record('vehicles', 'skipped_existing');

                continue;
            }

            $mobile = $this->normalizeMobile($h->MOBILE1);
            $customerExternalRef = $mobile
                ? 'MST_HISTORY:mobile:'.$mobile
                : 'MST_HISTORY:nomobile:'.$h->HIST_CODE;
            $customerId = $this->alreadyImported('customers', $customerExternalRef);

            if (! $customerId) {
                $this->record('vehicles', 'skipped_no_customer', $h->HIST_CODE, 'Run customers import first');

                continue;
            }

            $vehicleModelId = $this->alreadyImported('vehicle_models', 'MST_PRODUCT:'.$h->PRODUCT_CODE);
            $regNo = trim((string) $h->REG_NO);

            // Also filter obvious junk before even attempting an insert —
            // real Indian plates look like "GJ05LY3061" (2 letters, 1-2
            // digits, 1-3 letters, 1-4 digits). Something like "KOMAKI"
            // (a brand name, seen for real in this data) clearly isn't one.
            $looksLikeRealPlate = (bool) preg_match(
                '/^[A-Z]{2}[\s-]?\d{1,2}[\s-]?[A-Z]{0,3}[\s-]?\d{1,4}$/i',
                $regNo
            );
            if ($regNo !== '' && ! $looksLikeRealPlate) {
                $this->record('vehicles', 'flagged_junk_reg_no', $h->HIST_CODE, 'REG_NO='.$regNo);
                $regNo = '';
            }

            $realId = $this->insertVehicleWithRegNoFallback($h, $externalRef, $customerId, $vehicleModelId, $regNo);
            $this->markCreated('vehicles', $externalRef, $realId);
            $this->record('vehicles', 'created');
            if (! $vehicleModelId) {
                $this->record('vehicles', 'flagged_no_model_match', $h->HIST_CODE, 'PRODUCT_CODE='.$h->PRODUCT_CODE);
            }
        }
        $bar->finish();
        $this->newLine();
        $this->warn('Vehicles: some registration numbers were clearly junk (e.g. a brand name typed into the field) or collided with another vehicle already imported — those were imported as NON-RTO instead of crashing. Check storage/logs/laravel.log for "flagged_junk_reg_no" and "flagged_reg_no_collision" entries and correct the real plate number by hand where you know it.');
    }

    /** Insert a vehicle, falling back to NON-RTO if the registration number
     *  collides with one already imported (even after the regex pre-filter
     *  above — free-text legacy data can still produce genuine duplicates/
     *  typos we can't predict). Never let one bad row crash the whole run. */
    protected function insertVehicleWithRegNoFallback($h, string $externalRef, int $customerId, ?int $vehicleModelId, string $regNo): ?int
    {
        if ($this->dryRun) {
            return null; // nothing to actually insert in dry-run mode
        }

        $attempt = function (string $useRegNo, bool $forceNonRto) use ($h, $externalRef, $customerId, $vehicleModelId) {
            return DB::table('vehicles')->insertGetId([
                'customer_id' => $customerId,
                'registration_no' => $useRegNo ?: null,
                'is_non_rto' => $forceNonRto || $useRegNo === '',
                'chassis_no' => trim((string) $h->CHASSIS_NO) ?: null,
                'motor_battery_serial_no' => trim((string) $h->BATTERY_NO) ?: null,
                'vehicle_model_id' => $vehicleModelId ?: null,
                'external_ref' => $externalRef,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        };

        try {
            return $attempt($regNo, false);
        } catch (UniqueConstraintViolationException $e) {
            $this->record('vehicles', 'flagged_reg_no_collision', $h->HIST_CODE, 'REG_NO='.$regNo.' collided with an existing vehicle');

            return $attempt('', true);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // 5. Spare Parts (MST_ITEM) — [VERIFY] not sample-checked, column
    //    names only. Spot-check current_stock_qty / mrp after import.
    // ─────────────────────────────────────────────────────────────

    protected function importSpareParts(): void
    {
        // ACTIVE flag isn't reliably maintained in this installation
        // (314 total parts, only 1 flagged active) — same unreliable-flag
        // pattern already seen on TRN_JOBCARD.STATUS. Import everything;
        // an old job card referencing a now-discontinued part still needs
        // that part to exist, even if you never restock it going forward.
        $query = $this->legacy('MST_ITEM')->select('*');
        if ($this->limit) {
            $query->limit($this->limit);
        }
        $rows = $query->get();

        $bar = $this->output->createProgressBar($rows->count());
        foreach ($rows as $i) {
            $bar->advance();
            $externalRef = 'MST_ITEM:'.$i->ITEM_ID;
            if ($this->alreadyImported('spare_parts', $externalRef)) {
                $this->record('spare-parts', 'skipped_existing');

                continue;
            }

            $partName = trim((string) $i->PART_NAME);
            $partCode = trim((string) ($i->PART_NO ?: ('LEGACY-'.$i->ITEM_ID)));
            if ($partName === '') {
                $this->record('spare-parts', 'skipped_blank_name');

                continue;
            }

            if (DB::table('spare_parts')->where('part_code', $partCode)->exists()) {
                $partCode .= '-L'.$i->ITEM_ID; // avoid unique collision
            }

            $realId = $this->dryRun ? null : DB::table('spare_parts')->insertGetId([
                'part_name' => $partName,
                'part_code' => $partCode,
                'unit' => 'pcs', // [VERIFY] legacy unit not resolved this session
                'purchase_price' => (float) ($i->NDP_RATE ?? 0),
                'mrp' => (float) ($i->MRP_RATE ?? 0),
                'hsn_code' => trim((string) $i->HSN) ?: null,
                'current_stock_qty' => max(0, (int) ($i->OP_QTY ?? 0)),
                'reorder_level' => max(0, (int) ($i->MIN_QTY ?? 0)),
                'external_ref' => $externalRef,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->markCreated('spare_parts', $externalRef, $realId);
            $this->record('spare-parts', 'created');
        }
        $bar->finish();
        $this->newLine();
        $this->warn('Spare Parts: unit defaulted to "pcs" for all rows. OP_QTY is NULL/blank on most legacy rows we\'ve sampled, so most imported parts will land at 0 stock — this is a safe default (never negative/garbage), but it means you should do a real physical stock count and update quantities in the app after import, not rely on this number.');
    }

    // ─────────────────────────────────────────────────────────────
    // 6. Labour Master (MST_LITEM — confirmed via real data: 42 rows,
    //    ITEMNAME has clean human-readable names, RATEA is the rate
    //    directly on this table, no separate rate-join needed. The
    //    originally-assumed MST_ITEM_SUBLABOUR table is empty in this
    //    installation — don't revert to it.)
    // ─────────────────────────────────────────────────────────────

    protected function importLabourMaster(): void
    {
        $query = $this->legacy('MST_LITEM')->select('*');
        if ($this->limit) {
            $query->limit($this->limit);
        }
        $rows = $query->get();

        $bar = $this->output->createProgressBar($rows->count());
        foreach ($rows as $l) {
            $bar->advance();
            $externalRef = 'MST_LITEM:'.$l->LITEM_ID;
            if ($this->alreadyImported('labour_masters', $externalRef)) {
                $this->record('labour-master', 'skipped_existing');

                continue;
            }

            $name = trim((string) $l->ITEMNAME);
            if ($name === '') {
                $this->record('labour-master', 'skipped_blank_name');

                continue;
            }

            $realId = $this->dryRun ? null : DB::table('labour_masters')->insertGetId([
                'service_name' => $name,
                'category' => 'general', // [VERIFY] ITEMTYPE is a coded value (e.g. "M010001"), not human-readable — defaulting all rows to general, re-categorize manually
                'standard_rate' => (float) ($l->RATEA ?? 0),
                'taxable' => true,
                'external_ref' => $externalRef,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->markCreated('labour_masters', $externalRef, $realId);
            $this->record('labour-master', 'created');
        }
        $bar->finish();
        $this->newLine();
        $this->warn('Labour Master: category defaulted to "general" for all rows — review and re-categorize (battery/motor/brakes/electrical) manually.');
    }

    // ─────────────────────────────────────────────────────────────
    // 7. Job Cards + Complaints + Parts + Labour lines
    // ─────────────────────────────────────────────────────────────

    protected function importJobCards(): void
    {
        $query = $this->legacy('TRN_JOBCARD')->select('*')->orderBy('JOB_ID');
        if ($this->limit) {
            $query->limit($this->limit);
        }
        $jobcards = $query->get();

        $bar = $this->output->createProgressBar($jobcards->count());

        foreach ($jobcards as $j) {
            $bar->advance();

            // Include CO_YEAR — SERIES+JOB_NO alone can collide across
            // fiscal years (numbering resets annually in this system,
            // confirmed via real data: 135 job cards collided under the
            // old SERIES+JOB_NO-only key in a full dry run).
            $externalRef = 'TRN_JOBCARD:'.$j->CO_YEAR.'-'.$j->SERIES.'-'.$j->JOB_NO;
            if ($this->alreadyImported('job_cards', $externalRef)) {
                $this->record('job-cards', 'skipped_existing');

                continue;
            }

            $vehicleId = $this->alreadyImported('vehicles', 'MST_HISTORY:vehicle:'.$j->HIST_CODE);
            if (! $vehicleId) {
                $this->record('job-cards', 'skipped_no_vehicle', $externalRef, 'HIST_CODE='.$j->HIST_CODE);

                continue;
            }
            $customerId = DB::table('vehicles')->where('id', $vehicleId)->value('customer_id');

            $jobDate = $this->parseLegacyDate($j->JOB_DATE) ?? now()->toDateString();
            $status = match (trim((string) $j->STATUS)) {
                'C' => 'delivered',
                'A' => 'received',
                default => 'received',
            };
            $technicianId = $this->alreadyImported('employees', 'MST_EMP:'.$j->MECH_CODE);
            $supervisorId = $this->alreadyImported('employees', 'MST_EMP:'.$j->SUPERVISOR_CODE);

            $realJobCardId = $this->dryRun ? null : DB::transaction(function () use ($j, $externalRef, $vehicleId, $customerId, $jobDate, $status, $technicianId, $supervisorId) {
                return DB::table('job_cards')->insertGetId([
                    'job_card_no' => 'LEG-'.$j->CO_YEAR.'-'.$j->SERIES.'-'.$j->JOB_NO, // legacy number, doesn't match new JC-YYMMDD-NNN format — intentional, marks it as historical
                    'job_date' => $jobDate,
                    'customer_id' => $customerId,
                    'vehicle_id' => $vehicleId,
                    'odometer_reading_km' => is_numeric($j->KM) ? (int) $j->KM : null,
                    'job_type' => 'paid_service', // [VERIFY] no reliable legacy job-type mapping found this session
                    'assigned_technician_id' => $technicianId ?: null,
                    'supervisor_id' => $supervisorId ?: null,
                    'promised_delivery_date' => $this->parseLegacyDate($j->PROMISED_DATE),
                    'promised_delivery_time' => $this->parseLegacyTime($j->PROMISED_TIME),
                    'status' => $status,
                    'grand_total' => 0, // recomputed below from actual line items
                    'external_ref' => $externalRef,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
            $jobCardId = $this->markCreated('job_cards', $externalRef, $realJobCardId);
            $this->record('job-cards', 'created');

            // Complaints (also filtered by CO_YEAR — same collision risk)
            $complaints = $this->legacy('TRN_JOB_COMPLAIN')
                ->where('JOB_NO', $j->JOB_NO)->where('SERIES', $j->SERIES)
                ->where('CO_YEAR', $j->CO_YEAR)->get();
            foreach ($complaints as $c) {
                if (! $this->dryRun) {
                    DB::table('job_card_complaints')->insert([
                        'job_card_id' => $jobCardId,
                        'description' => trim((string) $c->COMP_NAME) ?: 'Imported complaint',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $this->record('job-cards', 'complaints_created');
            }

            // Parts (TRN_SUBPART, composite key JOB_SERIES + JOB_NO + CO_YEAR)
            $parts = $this->legacy('TRN_SUBPART')
                ->where('JOB_SERIES', $j->SERIES)->where('JOB_NO', $j->JOB_NO)
                ->where('CO_YEAR', $j->CO_YEAR)->get();
            $partsTotal = 0;
            foreach ($parts as $p) {
                $sparePartId = $this->alreadyImported('spare_parts', 'MST_ITEM:'.$this->findItemIdByPartNo($p->PART_NO));
                $lineTotal = (float) ($p->TOT_AMT ?? 0);
                $partsTotal += $lineTotal;
                if (! $sparePartId) {
                    $this->record('job-cards', 'flagged_part_unmatched', $externalRef, 'PART_NO='.$p->PART_NO);

                    continue; // skip the line rather than insert a broken FK
                }
                if (! $this->dryRun) {
                    DB::table('job_card_parts')->insert([
                        'job_card_id' => $jobCardId,
                        'spare_part_id' => $sparePartId,
                        'quantity' => max(1, (int) $p->QTY),
                        'mrp' => (float) ($p->RATE ?? 0),
                        'labor_charge' => 0,
                        'line_total' => $lineTotal,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $this->record('job-cards', 'part_lines_created');
            }

            // Labour (TRN_SUBLABOUR, same composite key + CO_YEAR)
            $labours = $this->legacy('TRN_SUBLABOUR')
                ->where('JOB_SERIES', $j->SERIES)->where('JOB_NO', $j->JOB_NO)
                ->where('CO_YEAR', $j->CO_YEAR)->get();
            $labourTotal = 0;
            foreach ($labours as $l) {
                $labourExternalRef = $this->findLabourExternalRefByCode($l->LABOUR_NO);
                $labourMasterId = $labourExternalRef ? $this->alreadyImported('labour_masters', $labourExternalRef) : null;
                $lineTotal = (float) ($l->TOT_AMT ?? 0);
                $labourTotal += $lineTotal;
                if (! $labourMasterId) {
                    $this->record('job-cards', 'flagged_labour_unmatched', $externalRef, 'LABOUR_NO='.$l->LABOUR_NO);

                    continue;
                }
                if (! $this->dryRun) {
                    DB::table('job_card_labours')->insert([
                        'job_card_id' => $jobCardId,
                        'labour_master_id' => $labourMasterId,
                        'technician_id' => $technicianId ?: null,
                        'rate' => (float) ($l->RATE ?? 0),
                        'qty' => max(1, (float) $l->QTY),
                        'line_total' => $lineTotal,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $this->record('job-cards', 'labour_lines_created');
            }

            if (! $this->dryRun) {
                DB::table('job_cards')->where('id', $jobCardId)->update([
                    'grand_total' => round($partsTotal + $labourTotal, 2),
                ]);
            }
        }
        $bar->finish();
        $this->newLine();
        $this->warn('Job Cards: job_type defaulted to "paid_service" for all rows, legacy numbers kept as "LEG-{series}-{no}" (not the new JC-YYMMDD-NNN format) so they read clearly as historical imports, not live jobs.');
    }

    /** MST_ITEM's PART_NO isn't the same as ITEM_ID (the external_ref key) —
     *  resolve via a small cached lookup rather than re-querying every row. */
    protected array $itemIdByPartNoCache = [];

    protected function findItemIdByPartNo(?string $partNo): ?int
    {
        $partNo = trim((string) $partNo);
        if ($partNo === '') {
            return null;
        }
        if (! array_key_exists($partNo, $this->itemIdByPartNoCache)) {
            $this->itemIdByPartNoCache[$partNo] = $this->legacy('MST_ITEM')
                ->where('PART_NO', $partNo)->value('ITEM_ID');
        }

        return $this->itemIdByPartNoCache[$partNo];
    }

    protected array $labourRefByCodeCache = [];

    protected function findLabourExternalRefByCode(?string $labourNo): ?string
    {
        $labourNo = trim((string) $labourNo);
        if ($labourNo === '') {
            return null;
        }
        if (! array_key_exists($labourNo, $this->labourRefByCodeCache)) {
            // TRN_SUBLABOUR.LABOUR_NO matches MST_LITEM.ITEMCODE directly
            // (confirmed via real sample data, e.g. 'L01GE00001').
            $litemId = $this->legacy('MST_LITEM')
                ->where('ITEMCODE', $labourNo)->value('LITEM_ID');
            $this->labourRefByCodeCache[$labourNo] = $litemId ? 'MST_LITEM:'.$litemId : null;
        }

        return $this->labourRefByCodeCache[$labourNo];
    }
}
