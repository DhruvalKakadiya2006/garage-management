<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;

trait SeedsVehicleCatalog
{
    /**
     * Create (or fetch) a Vehicle Catalog model under the given brand.
     */
    protected function makeCatalogModel(string $brandName, string $modelName, ?string $batteryType = 'lithium-ion'): VehicleModel
    {
        $brand = VehicleBrand::firstOrCreate(['name' => $brandName]);

        return VehicleModel::firstOrCreate(
            ['vehicle_brand_id' => $brand->id, 'name' => $modelName],
            ['battery_type' => $batteryType]
        );
    }

    /**
     * Create a customer-owned vehicle linked to a catalog model.
     */
    protected function makeCatalogVehicle(
        Customer $customer,
        string $registrationNo,
        string $brandName = 'Ola',
        string $modelName = 'S1 Pro',
        ?string $color = null,
    ): Vehicle {
        $model = $this->makeCatalogModel($brandName, $modelName);

        return Vehicle::create([
            'customer_id' => $customer->id,
            'registration_no' => $registrationNo,
            'vehicle_model_id' => $model->id,
            'color' => $color,
        ]);
    }
}
