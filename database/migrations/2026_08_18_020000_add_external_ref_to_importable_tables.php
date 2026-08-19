<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Introduce a nullable, unique, machine-friendly external identifier on every
 * table that participates in the reusable import/export foundation.
 *
 * The `external_ref` column is the stable import/export identifier used to
 * reconnect the same record on a future re-import, instead of depending
 * blindly on numeric primary keys. It is deliberately nullable so existing
 * records (and any import that does not supply one) are unaffected, and it
 * never replaces existing natural unique keys (mobile, job_card_no, part_code,
 * etc.) — it complements them.
 *
 * This migration only adds columns/indexes. It does not backfill anything and
 * does not change existing CRUD behaviour.
 */
return new class extends Migration
{
    private const TABLES = [
        'customers',
        'vehicles',
        'job_cards',
        'employees',
        'spare_parts',
        'labour_masters',
        'vehicle_brands',
        'vehicle_models',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('external_ref')->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropUnique(["{$table}_external_ref_unique"]);
                $blueprint->dropColumn('external_ref');
            });
        }
    }
};