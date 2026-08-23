<?php

namespace Database\Seeders;

use App\Models\CounterInvoice;
use App\Models\CounterInvoiceItem;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\JobCard;
use App\Models\JobCardLabour;
use App\Models\JobCardPart;
use App\Models\LabourMaster;
use App\Models\SparePart;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;

/**
 * Enhanced seeder that adds more diverse, meaningful sample data
 * Fixes issues:
 * - Company customers now show company_name properly
 * - Front desk staff no longer assigned as technicians
 * - Much more diverse scenarios and data
 */
class EnhancedSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Adding enhanced sample data...');

        $this->seedMoreCustomersAndVehicles();
        $this->seedMoreJobCards();
        $this->seedMoreCounterInvoices();

        $this->command->info('✅ Enhanced sample data added successfully!');
        $this->command->info('');
        $this->command->info('Updated Summary:');
        $this->command->info('- Customers: '.Customer::count());
        $this->command->info('- Vehicles: '.Vehicle::count());
        $this->command->info('- Job Cards: '.JobCard::count());
        $this->command->info('- Counter Invoices: '.CounterInvoice::count());
    }

    private function seedMoreCustomersAndVehicles(): void
    {
        // Get or create brands and models
        $olaBrand = VehicleBrand::where('name', 'Ola')->first();
        $atherBrand = VehicleBrand::where('name', 'Ather')->first();
        $tvsBrand = VehicleBrand::where('name', 'TVS')->first();
        $bajajBrand = VehicleBrand::where('name', 'Bajaj')->first();
        $heroBrand = VehicleBrand::firstOrCreate(['name' => 'Hero Vida']);
        $ampereBrand = VehicleBrand::firstOrCreate(['name' => 'Ampere']);

        $olaS1 = VehicleModel::where('name', 'S1 Pro')->first();
        $olaS1Air = VehicleModel::firstOrCreate(
            ['vehicle_brand_id' => $olaBrand->id, 'name' => 'S1 Air'],
            ['battery_type' => 'lithium-ion']
        );
        $ather450x = VehicleModel::where('name', '450X')->first();
        $ather450s = VehicleModel::firstOrCreate(
            ['vehicle_brand_id' => $atherBrand->id, 'name' => '450S'],
            ['battery_type' => 'lithium-ion']
        );
        $tvsIqube = VehicleModel::where('name', 'iQube')->first();
        $chetak = VehicleModel::where('name', 'Chetak')->first();
        $heroV1 = VehicleModel::firstOrCreate(
            ['vehicle_brand_id' => $heroBrand->id, 'name' => 'V1 Pro'],
            ['battery_type' => 'lithium-ion']
        );
        $ampereMagnus = VehicleModel::firstOrCreate(
            ['vehicle_brand_id' => $ampereBrand->id, 'name' => 'Magnus'],
            ['battery_type' => 'lithium-ion']
        );

        $customers = [
            // Individual customers with Indian names
            [
                'customer' => [
                    'first_name' => 'Rajesh',
                    'last_name' => 'Kumar',
                    'mobile' => '+91 98765 10001',
                    'email' => 'rajesh.kumar@gmail.com',
                    'address' => 'Flat 301, Shanti Heights, Varachha Road',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $ather450x->id, 'registration_no' => 'GJ-05-MN-8821', 'color' => 'Space Grey', 'is_non_rto' => false],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Sneha',
                    'last_name' => 'Patel',
                    'mobile' => '+91 98765 10002',
                    'email' => 'sneha.patel@yahoo.com',
                    'address' => 'B-12, Green Park Society, Althan',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $olaS1Air->id, 'registration_no' => 'GJ-05-PQ-3344', 'color' => 'Jet Black', 'is_non_rto' => false],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Harish',
                    'last_name' => 'Sharma',
                    'mobile' => '+91 98765 10003',
                    'email' => 'harish.sharma@outlook.com',
                    'address' => '25, Lal Darwaja, Old City',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $tvsIqube->id, 'registration_no' => 'GJ-05-RS-7766', 'color' => 'Pearl White', 'is_non_rto' => false],
                    ['model_id' => $chetak->id, 'registration_no' => 'GJ-05-TU-9988', 'color' => 'Brooklyn Blue', 'is_non_rto' => false],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Kavita',
                    'last_name' => 'Joshi',
                    'mobile' => '+91 98765 10004',
                    'email' => null,
                    'address' => 'Plot 45, Utran Village',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $heroV1->id, 'registration_no' => 'GJ-05-VW-2211', 'color' => 'Neon Cyan', 'is_non_rto' => false],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Ankit',
                    'last_name' => 'Verma',
                    'mobile' => '+91 98765 10005',
                    'email' => 'ankit.v@rediffmail.com',
                    'address' => 'C-204, Royal Residency, Piplod',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $olaS1->id, 'registration_no' => null, 'color' => 'Porcelain White', 'is_non_rto' => true],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Meera',
                    'last_name' => 'Desai',
                    'mobile' => '+91 98765 10006',
                    'email' => 'meera.desai@gmail.com',
                    'address' => '15/A, Textile Market, Ring Road',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => '24AABCD7890E1Z3',
                ],
                'vehicles' => [
                    ['model_id' => $ather450s->id, 'registration_no' => 'GJ-05-XY-5544', 'color' => 'Cosmic Grey', 'is_non_rto' => false],
                    ['model_id' => $ather450x->id, 'registration_no' => 'GJ-05-ZA-8899', 'color' => 'True Red', 'is_non_rto' => false],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Arun',
                    'last_name' => 'Nair',
                    'mobile' => '+91 98765 10007',
                    'email' => 'arun.nair@icloud.com',
                    'address' => 'Shop 8, Athwa Lines',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $ampereMagnus->id, 'registration_no' => 'GJ-05-BC-1122', 'color' => 'Electric Blue', 'is_non_rto' => false],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Pooja',
                    'last_name' => 'Agarwal',
                    'mobile' => '+91 98765 10008',
                    'email' => 'pooja.agarwal@yahoo.in',
                    'address' => 'D-45, City Light Town',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $olaS1->id, 'registration_no' => 'GJ-05-DE-3366', 'color' => 'Coral Glam', 'is_non_rto' => false],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Sanjay',
                    'last_name' => 'Gupta',
                    'mobile' => '+91 98765 10009',
                    'email' => 'sanjay.gupta@gmail.com',
                    'address' => '78, Nanpura Main Road',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $tvsIqube->id, 'registration_no' => 'GJ-05-FG-7788', 'color' => 'Metallic Blue', 'is_non_rto' => false],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Divya',
                    'last_name' => 'Pandey',
                    'mobile' => '+91 98765 10010',
                    'email' => null,
                    'address' => 'Bungalow 5, Vesu Gardens',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $chetak->id, 'registration_no' => null, 'color' => 'Hazelnut', 'is_non_rto' => true],
                ],
            ],
        ];

        foreach ($customers as $data) {
            $customer = Customer::firstOrCreate(
                ['mobile' => $data['customer']['mobile']],
                $data['customer']
            );

            foreach ($data['vehicles'] as $vehicleData) {
                Vehicle::firstOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'vehicle_model_id' => $vehicleData['model_id'],
                        'registration_no' => $vehicleData['registration_no'],
                    ],
                    [
                        'color' => $vehicleData['color'],
                        'is_non_rto' => $vehicleData['is_non_rto'],
                    ]
                );
            }
        }

        // Business/Counter customers - with company_name properly set
        $businessCustomers = [
            [
                'company_name' => 'Surat EV Dealership',
                'mobile' => '+91 98765 20001',
                'email' => 'sales@suratevdealership.com',
                'address' => 'Plot 12, GIDC Industrial Area',
                'city' => 'Surat',
                'customer_type' => 'counter',
                'gstin' => '24PQRST1234U1V5',
            ],
            [
                'company_name' => 'Gujarat Auto Spares',
                'mobile' => '+91 98765 20002',
                'email' => 'info@gujaratautospares.com',
                'address' => 'Shop 15-16, Auto Market, Udhna',
                'city' => 'Surat',
                'customer_type' => 'counter',
                'gstin' => '24WXYZ56789A1B2',
            ],
            [
                'first_name' => 'Ramesh',
                'last_name' => 'Bhai',
                'mobile' => '+91 98765 20003',
                'email' => null,
                'address' => null,
                'city' => null,
                'customer_type' => 'counter',
                'gstin' => null,
            ],
        ];

        foreach ($businessCustomers as $customerData) {
            Customer::firstOrCreate(
                ['mobile' => $customerData['mobile']],
                $customerData
            );
        }
    }

    private function seedMoreJobCards(): void
    {
        $employees = Employee::where('role', 'technician')->get();
        $supervisor = Employee::where('role', 'supervisor')->first();
        $labours = LabourMaster::all();
        $parts = SparePart::all();

        if ($employees->isEmpty() || $labours->isEmpty() || $parts->isEmpty()) {
            $this->command->warn('Skipping additional job cards - missing required data');

            return;
        }

        $jobCardsData = [
            // Warranty service
            [
                'mobile' => '+91 98765 10001',
                'odometer' => 850,
                'job_type' => 'warranty',
                'days_ago' => 5,
                'status' => 'in_progress',
                'urgent' => false,
                'labours' => [
                    ['service' => 'Battery Health Check', 'qty' => 1],
                ],
                'parts' => [],
            ],
            // Free service
            [
                'mobile' => '+91 98765 10002',
                'odometer' => 500,
                'job_type' => 'free_service',
                'days_ago' => 3,
                'status' => 'ready_for_delivery',
                'urgent' => false,
                'labours' => [
                    ['service' => 'General Service', 'qty' => 1],
                ],
                'parts' => [],
            ],
            // Breakdown - urgent
            [
                'mobile' => '+91 98765 10003',
                'odometer' => 3200,
                'job_type' => 'breakdown',
                'days_ago' => 1,
                'status' => 'in_progress',
                'urgent' => true,
                'labours' => [
                    ['service' => 'Controller Diagnosis', 'qty' => 1],
                    ['service' => 'Wiring Harness Check', 'qty' => 1],
                ],
                'parts' => [
                    ['name' => 'Battery Cooling Fan', 'qty' => 1],
                ],
            ],
            // Paid service with multiple parts
            [
                'mobile' => '+91 98765 10004',
                'odometer' => 2100,
                'job_type' => 'paid_service',
                'days_ago' => 8,
                'status' => 'delivered',
                'urgent' => false,
                'labours' => [
                    ['service' => 'Tire Replacement', 'qty' => 2],
                    ['service' => 'Brake Adjustment', 'qty' => 1],
                ],
                'parts' => [
                    ['name' => 'Tire - Front (90/90-12)', 'qty' => 1],
                    ['name' => 'Tire - Rear (90/100-12)', 'qty' => 1],
                    ['name' => 'Front Brake Pad', 'qty' => 2],
                ],
            ],
            // Insurance claim
            [
                'mobile' => '+91 98765 10005',
                'odometer' => 1500,
                'job_type' => 'insurance_claim',
                'days_ago' => 12,
                'status' => 'delivered',
                'urgent' => false,
                'labours' => [
                    ['service' => 'Body Panel Replacement', 'qty' => 3],
                ],
                'parts' => [
                    ['name' => 'Side Panel (Left)', 'qty' => 1],
                    ['name' => 'Side Panel (Right)', 'qty' => 1],
                    ['name' => 'LED Headlight Assembly', 'qty' => 1],
                ],
            ],
            // AMC service
            [
                'mobile' => '+91 98765 10006',
                'odometer' => 4500,
                'job_type' => 'amc',
                'days_ago' => 6,
                'status' => 'delivered',
                'urgent' => false,
                'labours' => [
                    ['service' => 'General Service', 'qty' => 1],
                    ['service' => 'Battery Health Check', 'qty' => 1],
                ],
                'parts' => [
                    ['name' => 'Chain Set', 'qty' => 1],
                ],
            ],
            // Just received today
            [
                'mobile' => '+91 98765 10007',
                'odometer' => 980,
                'job_type' => 'paid_service',
                'days_ago' => 0,
                'status' => 'received',
                'urgent' => false,
                'labours' => [],
                'parts' => [],
            ],
            // Waiting for parts - charger issue
            [
                'mobile' => '+91 98765 10008',
                'odometer' => 2890,
                'job_type' => 'paid_service',
                'days_ago' => 4,
                'status' => 'waiting_for_parts',
                'urgent' => false,
                'labours' => [
                    ['service' => 'Wiring Harness Check', 'qty' => 1],
                ],
                'parts' => [],
            ],
            // Display and electrical issue
            [
                'mobile' => '+91 98765 10009',
                'odometer' => 3650,
                'job_type' => 'paid_service',
                'days_ago' => 2,
                'status' => 'in_progress',
                'urgent' => false,
                'labours' => [
                    ['service' => 'Display Panel Repair', 'qty' => 1],
                ],
                'parts' => [
                    ['name' => 'Display Screen', 'qty' => 1],
                ],
            ],
            // Motor service - delivered
            [
                'mobile' => '+91 98765 10010',
                'odometer' => 5200,
                'job_type' => 'paid_service',
                'days_ago' => 18,
                'status' => 'delivered',
                'urgent' => false,
                'labours' => [
                    ['service' => 'Motor Service', 'qty' => 1],
                ],
                'parts' => [
                    ['name' => 'Motor Controller PCB', 'qty' => 1],
                ],
            ],
        ];

        $jobCardCounter = JobCard::count() + 1;

        foreach ($jobCardsData as $jobData) {
            $customer = Customer::where('mobile', $jobData['mobile'])->first();

            if (! $customer || $customer->vehicles->isEmpty()) {
                continue;
            }

            $vehicle = $customer->vehicles->first();
            $technician = $employees->random();

            $jobCard = JobCard::create([
                'job_card_no' => 'JC-'.now()->format('Ymd').'-'.str_pad($jobCardCounter, 3, '0', STR_PAD_LEFT),
                'job_date' => now()->subDays($jobData['days_ago']),
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'odometer_reading_km' => $jobData['odometer'],
                'job_type' => $jobData['job_type'],
                'assigned_technician_id' => $technician->id,
                'supervisor_id' => ($jobData['job_type'] === 'insurance_claim' || $jobData['urgent']) ? $supervisor->id : null,
                'status' => $jobData['status'],
                'gst_applicable' => $jobData['job_type'] !== 'free_service' && $jobData['job_type'] !== 'warranty',
                'grand_total' => 0,
            ]);

            // Add labours
            foreach ($jobData['labours'] as $labourData) {
                $labour = $labours->where('service_name', $labourData['service'])->first();
                if ($labour) {
                    JobCardLabour::create([
                        'job_card_id' => $jobCard->id,
                        'labour_master_id' => $labour->id,
                        'technician_id' => $technician->id,
                        'rate' => $labour->standard_rate,
                        'qty' => $labourData['qty'],
                        'line_total' => $labour->standard_rate * $labourData['qty'],
                    ]);
                }
            }

            // Add parts
            foreach ($jobData['parts'] as $partData) {
                $part = $parts->where('part_name', $partData['name'])->first();
                if ($part) {
                    JobCardPart::create([
                        'job_card_id' => $jobCard->id,
                        'spare_part_id' => $part->id,
                        'quantity' => $partData['qty'],
                        'mrp' => $part->mrp,
                        'labor_charge' => $part->labor_charge,
                        'line_total' => ($part->mrp + $part->labor_charge) * $partData['qty'],
                    ]);
                }
            }

            $jobCardCounter++;
        }
    }

    private function seedMoreCounterInvoices(): void
    {
        $parts = SparePart::all();

        if ($parts->isEmpty()) {
            return;
        }

        $invoicesData = [
            // Business customer - bulk purchase
            [
                'mobile' => '+91 98765 20001',
                'days_ago' => 3,
                'discount' => 500.00,
                'items' => [
                    ['name' => 'Battery Fuse 30A', 'qty' => 20],
                    ['name' => 'Chain Set', 'qty' => 5],
                    ['name' => 'Front Brake Pad', 'qty' => 10],
                    ['name' => 'Rear Brake Pad', 'qty' => 10],
                ],
            ],
            // Another business customer
            [
                'mobile' => '+91 98765 20002',
                'days_ago' => 1,
                'discount' => 300.00,
                'items' => [
                    ['name' => 'Battery BMS Unit', 'qty' => 2],
                    ['name' => 'Charger Port Assembly', 'qty' => 3],
                ],
            ],
            // Walk-in customer - small purchase
            [
                'mobile' => '+91 98765 20003',
                'days_ago' => 0,
                'discount' => 0,
                'items' => [
                    ['name' => 'Seat Cover', 'qty' => 1],
                    ['name' => 'Footrest Rubber', 'qty' => 2],
                ],
            ],
        ];

        $invoiceCounter = CounterInvoice::count() + 1;

        foreach ($invoicesData as $invoiceData) {
            $customer = Customer::where('mobile', $invoiceData['mobile'])->first();

            if (! $customer) {
                continue;
            }

            $invoice = CounterInvoice::create([
                'invoice_no' => 'CI-'.now()->format('Ymd').'-'.str_pad($invoiceCounter, 3, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'invoice_date' => now()->subDays($invoiceData['days_ago']),
                'gst_applicable' => true,
                'discount_amount' => $invoiceData['discount'],
                'grand_total' => 0,
            ]);

            foreach ($invoiceData['items'] as $itemData) {
                $part = $parts->where('part_name', $itemData['name'])->first();
                if ($part) {
                    CounterInvoiceItem::create([
                        'counter_invoice_id' => $invoice->id,
                        'spare_part_id' => $part->id,
                        'quantity' => $itemData['qty'],
                        'mrp' => $part->mrp,
                        'line_total' => $part->mrp * $itemData['qty'],
                    ]);
                }
            }

            $invoiceCounter++;
        }
    }
}
