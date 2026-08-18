<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Enforce current_stock_qty >= 0 at the database level as a permanent
     * safety net behind the application-level StockValidator / model guards.
     *
     * SQLite cannot ALTER TABLE ... ADD CONSTRAINT, so a CHECK constraint is
     * added by rebuilding the table: create a new table carrying the
     * constraint, copy every row across, drop the old table and rename the new
     * one into place. Foreign keys must be disabled during the swap, otherwise
     * the implicit DELETE performed by DROP TABLE would violate the RESTRICT
     * references from job_card_parts / counter_invoice_items. Integrity is
     * re-verified with PRAGMA foreign_key_check afterwards.
     */
    public function up(): void
    {
        $this->swapSparePartsTable(withCheck: true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->swapSparePartsTable(withCheck: false);
    }

    /**
     * Recreate spare_parts with or without the stock CHECK constraint.
     */
    private function swapSparePartsTable(bool $withCheck): void
    {
        $temporary = 'spare_parts_rebuild';

        $check = $withCheck ? ', CHECK ("current_stock_qty" >= 0)' : '';

        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            DB::statement('DROP TABLE IF EXISTS '.$temporary);

            DB::statement(sprintf(
                'CREATE TABLE "%s" (
                    "id" integer primary key autoincrement not null,
                    "part_name" varchar not null,
                    "part_code" varchar not null,
                    "category" varchar,
                    "compatible_vehicle_brand" varchar,
                    "unit" varchar not null,
                    "purchase_price" numeric not null,
                    "mrp" numeric not null,
                    "labor_charge" numeric not null default (\'0\'),
                    "hsn_code" varchar,
                    "gst_rate" numeric,
                    "current_stock_qty" integer not null default (\'0\'),
                    "reorder_level" integer not null default (\'0\'),
                    "created_at" datetime,
                    "updated_at" datetime
                    %s
                )',
                $temporary,
                $check,
            ));

            DB::statement(sprintf(
                'INSERT INTO "%s"
                    (id, part_name, part_code, category, compatible_vehicle_brand, unit,
                     purchase_price, mrp, labor_charge, hsn_code, gst_rate,
                     current_stock_qty, reorder_level, created_at, updated_at)
                 SELECT id, part_name, part_code, category, compatible_vehicle_brand, unit,
                        purchase_price, mrp, labor_charge, hsn_code, gst_rate,
                        current_stock_qty, reorder_level, created_at, updated_at
                 FROM spare_parts',
                $temporary,
            ));

            DB::statement('DROP TABLE spare_parts');
            DB::statement(sprintf('ALTER TABLE "%s" RENAME TO spare_parts', $temporary));

            // Restore the unique index the schema builder originally created
            DB::statement('CREATE UNIQUE INDEX spare_parts_part_code_unique ON spare_parts ("part_code")');
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }

        $violations = DB::select('PRAGMA foreign_key_check');
        if (! empty($violations)) {
            throw new RuntimeException('Foreign key integrity check failed after rebuilding spare_parts.');
        }
    }
};
