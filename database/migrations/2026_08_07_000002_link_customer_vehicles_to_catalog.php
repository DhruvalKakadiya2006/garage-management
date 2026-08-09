<?php

use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link each customer-owned vehicle to a Vehicle Catalog model, replacing the
     * old free-text vehicle_brand / model / battery_type columns.
     *
     * Existing rows are matched against the catalog best-effort:
     *   1. exact (case-insensitive) brand + model name pair,
     *   2. a unique (case-insensitive) model name match on its own,
     *   3. fallback to the "Other / Custom-built" -> "Other" catalog entry so no
     *      vehicle loses its customer linkage.
     */
    public function up(): void
    {
        // 1. Add the catalog link column (nullable so existing rows can be backfilled).
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('vehicle_model_id')->nullable()->after('customer_id');
        });

        // 2. Best-effort backfill from the old free-text columns.
        $this->backfillVehicleModels();

        // 3. Enforce the link (restrict delete) and drop the old columns.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('vehicle_model_id')->references('id')->on('vehicle_models')->onDelete('restrict');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['vehicle_brand', 'model', 'battery_type']);
        });
    }

    /**
     * Reverse the migration: restore the free-text columns populated from the
     * linked catalog rows, then remove the catalog link.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('vehicle_brand')->nullable();
            $table->string('model')->nullable();
            $table->enum('battery_type', ['lithium-ion', 'lead-acid', 'other'])->nullable();
        });

        DB::table('vehicles')
            ->join('vehicle_models', 'vehicle_models.id', '=', 'vehicles.vehicle_model_id')
            ->join('vehicle_brands', 'vehicle_brands.id', '=', 'vehicle_models.vehicle_brand_id')
            ->select(['vehicles.id', 'vehicle_brands.name as brand_name', 'vehicle_models.name as model_name', 'vehicle_models.battery_type'])
            ->orderBy('vehicles.id')
            ->each(function ($row) {
                DB::table('vehicles')->where('id', $row->id)->update([
                    'vehicle_brand' => $row->brand_name,
                    'model' => $row->model_name,
                    'battery_type' => $row->battery_type,
                ]);
            });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['vehicle_model_id']);
            $table->dropColumn('vehicle_model_id');
        });
    }

    private function backfillVehicleModels(): void
    {
        $models = VehicleModel::with('vehicleBrand')->get();

        // name|brand -> model id (lowercased) for exact pair matching
        $byPair = [];
        // model name -> model ids (lowercased) for unique-name matching
        $byName = [];

        foreach ($models as $model) {
            $brandName = strtolower(trim((string) $model->vehicleBrand?->name));
            $modelName = strtolower(trim((string) $model->name));

            if ($modelName !== '') {
                if ($brandName !== '') {
                    $byPair[$brandName.'|'.$modelName] = $model->id;
                }
                $byName[$modelName][] = $model->id;
            }
        }

        // Fallback catalog entry from the Phase 3a seeder, created lazily so an
        // empty catalog (e.g. fresh installs, tests) is never polluted.
        $otherModel = null;

        DB::table('vehicles')->select(['id', 'vehicle_brand', 'model'])->orderBy('id')->each(function ($vehicle) use ($byPair, $byName, &$otherModel) {
            $brand = strtolower(trim((string) $vehicle->vehicle_brand));
            $model = strtolower(trim((string) $vehicle->model));

            $modelId = null;

            if ($brand !== '' && $model !== '' && isset($byPair[$brand.'|'.$model])) {
                $modelId = $byPair[$brand.'|'.$model];
            } elseif ($model !== '' && isset($byName[$model]) && count($byName[$model]) === 1) {
                $modelId = $byName[$model][0];
            }

            if (! $modelId) {
                if (! $otherModel) {
                    $otherBrand = VehicleBrand::firstOrCreate(['name' => 'Other / Custom-built']);
                    $otherModel = VehicleModel::firstOrCreate(
                        ['vehicle_brand_id' => $otherBrand->id, 'name' => 'Other'],
                    );
                }

                $modelId = $otherModel->id;
            }

            DB::table('vehicles')->where('id', $vehicle->id)->update(['vehicle_model_id' => $modelId]);
        });
    }
};
