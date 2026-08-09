<?php

namespace Database\Seeders;

use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;

class VehicleCatalogSeeder extends Seeder
{
    /**
     * Starter data for the Vehicle Catalog: common EV brands/models sold and
     * serviced in India. Staff can add more via the UI — this is a starting
     * point, not a definitive list. Uses firstOrCreate so re-seeding is safe.
     */
    public function run(): void
    {
        $catalog = [
            'Ola' => [
                'S1' => 'lithium-ion',
                'S1 Pro' => 'lithium-ion',
                'S1 Air' => 'lithium-ion',
                'S1 X' => 'lithium-ion',
            ],
            'Ather' => [
                '450X' => 'lithium-ion',
                '450S' => 'lithium-ion',
                'Rizta' => 'lithium-ion',
            ],
            'TVS' => [
                'iQube' => 'lithium-ion',
                'iQube ST' => 'lithium-ion',
            ],
            'Bajaj' => [
                'Chetak' => 'lithium-ion',
            ],
            'Hero Vida' => [
                'V1' => 'lithium-ion',
                'V1 Pro' => 'lithium-ion',
            ],
            'Ampere' => [
                'Magnus' => 'lithium-ion',
                'Zeal' => 'lithium-ion',
            ],
            'Other / Custom-built' => [
                'Other' => null,
            ],
        ];

        foreach ($catalog as $brandName => $models) {
            $brand = VehicleBrand::firstOrCreate(['name' => $brandName]);

            foreach ($models as $modelName => $batteryType) {
                VehicleModel::firstOrCreate(
                    ['vehicle_brand_id' => $brand->id, 'name' => $modelName],
                    ['battery_type' => $batteryType]
                );
            }
        }
    }
}
