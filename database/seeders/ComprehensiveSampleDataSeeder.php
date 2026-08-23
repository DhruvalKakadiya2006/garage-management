<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\CounterInvoice;
use App\Models\CounterInvoiceItem;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\JobCard;
use App\Models\JobCardLabour;
use App\Models\JobCardPart;
use App\Models\LabourMaster;
use App\Models\Payment;
use App\Models\SparePart;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive sample data seeder that covers all tables and scenarios:
 * - Regular and counter customers
 * - Multiple vehicles per customer
 * - RTO and NON-RTO vehicles
 * - Pending, in-progress, and completed job cards
 * - Fully paid, partially paid, and unpaid invoices
 * - Counter invoices (parts-only sales)
 * - Various spare parts with different stock levels
 * - Labour masters
 * - Employees/technicians
 * - Company profile
 */
class ComprehensiveSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedCompanyProfile();
            $this->seedEmployees();
            $this->seedSpareParts();
            $this->seedLabourMasters();
            $this->seedCustomersAndVehicles();
            $this->seedJobCardsWithVariousStatuses();
            $this->seedCounterInvoices();
            $this->seedPayments();
        });

        $this->command->info('✅ Sample data seeded successfully!');
        $this->command->info('');
        $this->command->info('Summary:');
        $this->command->info('- Customers: '.Customer::count());
        $this->command->info('- Vehicles: '.Vehicle::count());
        $this->command->info('- Job Cards: '.JobCard::count());
        $this->command->info('- Counter Invoices: '.CounterInvoice::count());
        $this->command->info('- Payments: '.Payment::count());
        $this->command->info('- Spare Parts: '.SparePart::count());
        $this->command->info('- Labour Masters: '.LabourMaster::count());
        $this->command->info('- Employees: '.Employee::count());
    }

    private function seedCompanyProfile(): void
    {
        CompanyProfile::firstOrCreate(
            [],
            [
                'company_name' => 'Kakadiya EV Hub',
                'address' => 'Shop No. 12, EV Complex, Ring Road',
                'city' => 'Surat',
                'state' => 'Gujarat',
                'pincode' => '395007',
                'phone' => '+91 98765 43210',
                'email' => 'contact@kakadiyaevhub.com',
                'gstin' => '24ABCDE1234F1Z5',
                'bank_name' => 'State Bank of India',
                'bank_account_no' => '1234567890',
                'bank_ifsc' => 'SBIN0001234',
                'terms_and_conditions' => "1. Payment is due upon completion of service.\n2. Parts replaced carry manufacturer warranty.\n3. Labour charges are non-refundable.\n4. Vehicle must be collected within 48 hours of notification.",
            ]
        );
    }

    private function seedEmployees(): void
    {
        $employees = [
            // Technicians
            ['name' => 'Rajesh Patel', 'mobile' => '+91 98765 11111', 'role' => 'technician', 'specialization' => 'motor_controller', 'active' => true, 'join_date' => now()->subYears(5)],
            ['name' => 'Amit Kumar', 'mobile' => '+91 98765 22222', 'role' => 'technician', 'specialization' => 'battery', 'active' => true, 'join_date' => now()->subYears(3)],
            ['name' => 'Kiran Joshi', 'mobile' => '+91 98765 33333', 'role' => 'technician', 'specialization' => 'electrical', 'active' => true, 'join_date' => now()->subYears(2)],
            ['name' => 'Deepak Mehta', 'mobile' => '+91 98765 88888', 'role' => 'technician', 'specialization' => 'general', 'active' => true, 'join_date' => now()->subYear()],

            // Helpers
            ['name' => 'Suresh Sharma', 'mobile' => '+91 98765 99999', 'role' => 'helper', 'specialization' => 'general', 'active' => true, 'join_date' => now()->subMonths(8)],
            ['name' => 'Ramesh Yadav', 'mobile' => '+91 98765 77777', 'role' => 'helper', 'specialization' => 'general', 'active' => true, 'join_date' => now()->subMonths(6)],

            // Supervisor
            ['name' => 'Vikram Singh', 'mobile' => '+91 98765 55555', 'role' => 'supervisor', 'specialization' => 'electrical', 'active' => true, 'join_date' => now()->subYears(7)],

            // Front Desk / Admin
            ['name' => 'Priya Desai', 'mobile' => '+91 98765 44444', 'role' => 'front_desk', 'specialization' => null, 'active' => true, 'join_date' => now()->subYears(2)],
            ['name' => 'Anjali Kapoor', 'mobile' => '+91 98765 66666', 'role' => 'admin', 'specialization' => null, 'active' => true, 'join_date' => now()->subYears(4)],
        ];

        foreach ($employees as $employee) {
            Employee::firstOrCreate(['mobile' => $employee['mobile']], $employee);
        }
    }

    private function seedSpareParts(): void
    {
        $parts = [
            // High stock items
            ['part_name' => 'Front Brake Pad', 'part_code' => 'BRK-001', 'category' => 'Brakes', 'unit' => 'set', 'purchase_price' => 650.00, 'mrp' => 850.00, 'labor_charge' => 100.00, 'hsn_code' => '87083010', 'gst_rate' => 18.00, 'current_stock_qty' => 50, 'reorder_level' => 10],
            ['part_name' => 'Rear Brake Pad', 'part_code' => 'BRK-002', 'category' => 'Brakes', 'unit' => 'set', 'purchase_price' => 580.00, 'mrp' => 780.00, 'labor_charge' => 100.00, 'hsn_code' => '87083010', 'gst_rate' => 18.00, 'current_stock_qty' => 45, 'reorder_level' => 10],
            ['part_name' => 'Chain Set', 'part_code' => 'CHN-001', 'category' => 'Drive', 'unit' => 'set', 'purchase_price' => 950.00, 'mrp' => 1200.00, 'labor_charge' => 150.00, 'hsn_code' => '87149900', 'gst_rate' => 18.00, 'current_stock_qty' => 30, 'reorder_level' => 5],
            ['part_name' => 'Battery Fuse 30A', 'part_code' => 'FUS-030', 'category' => 'Electrical', 'unit' => 'pcs', 'purchase_price' => 90.00, 'mrp' => 150.00, 'labor_charge' => 50.00, 'hsn_code' => '85361010', 'gst_rate' => 18.00, 'current_stock_qty' => 100, 'reorder_level' => 20],
            ['part_name' => 'LED Headlight Assembly', 'part_code' => 'LGT-001', 'category' => 'Lighting', 'unit' => 'pcs', 'purchase_price' => 1950.00, 'mrp' => 2500.00, 'labor_charge' => 200.00, 'hsn_code' => '85122000', 'gst_rate' => 18.00, 'current_stock_qty' => 15, 'reorder_level' => 3],

            // Medium stock items
            ['part_name' => 'Battery Cooling Fan', 'part_code' => 'FAN-001', 'category' => 'Battery', 'unit' => 'pcs', 'purchase_price' => 1350.00, 'mrp' => 1800.00, 'labor_charge' => 250.00, 'hsn_code' => '85016100', 'gst_rate' => 18.00, 'current_stock_qty' => 8, 'reorder_level' => 2],
            ['part_name' => 'Motor Controller PCB', 'part_code' => 'PCB-001', 'category' => 'Electronics', 'unit' => 'pcs', 'purchase_price' => 3500.00, 'mrp' => 4500.00, 'labor_charge' => 500.00, 'hsn_code' => '85372000', 'gst_rate' => 18.00, 'current_stock_qty' => 5, 'reorder_level' => 2],
            ['part_name' => 'Display Screen', 'part_code' => 'DSP-001', 'category' => 'Electronics', 'unit' => 'pcs', 'purchase_price' => 2400.00, 'mrp' => 3200.00, 'labor_charge' => 300.00, 'hsn_code' => '85258020', 'gst_rate' => 18.00, 'current_stock_qty' => 6, 'reorder_level' => 2],

            // Low stock items (below minimum)
            ['part_name' => 'Rear View Mirror (Left)', 'part_code' => 'MIR-L01', 'category' => 'Body Parts', 'unit' => 'pcs', 'purchase_price' => 320.00, 'mrp' => 450.00, 'labor_charge' => 80.00, 'hsn_code' => '87141010', 'gst_rate' => 18.00, 'current_stock_qty' => 2, 'reorder_level' => 5],
            ['part_name' => 'Rear View Mirror (Right)', 'part_code' => 'MIR-R01', 'category' => 'Body Parts', 'unit' => 'pcs', 'purchase_price' => 320.00, 'mrp' => 450.00, 'labor_charge' => 80.00, 'hsn_code' => '87141010', 'gst_rate' => 18.00, 'current_stock_qty' => 3, 'reorder_level' => 5],
            ['part_name' => 'Throttle Grip', 'part_code' => 'THR-001', 'category' => 'Controls', 'unit' => 'pcs', 'purchase_price' => 480.00, 'mrp' => 650.00, 'labor_charge' => 100.00, 'hsn_code' => '87149900', 'gst_rate' => 18.00, 'current_stock_qty' => 1, 'reorder_level' => 5],

            // Various other parts
            ['part_name' => 'Battery BMS Unit', 'part_code' => 'BMS-001', 'category' => 'Battery', 'unit' => 'pcs', 'purchase_price' => 4500.00, 'mrp' => 5800.00, 'labor_charge' => 600.00, 'hsn_code' => '85371010', 'gst_rate' => 18.00, 'current_stock_qty' => 4, 'reorder_level' => 2],
            ['part_name' => 'Tire - Front (90/90-12)', 'part_code' => 'TYR-F90', 'category' => 'Tires', 'unit' => 'pcs', 'purchase_price' => 1450.00, 'mrp' => 1850.00, 'labor_charge' => 150.00, 'hsn_code' => '40111000', 'gst_rate' => 18.00, 'current_stock_qty' => 12, 'reorder_level' => 4],
            ['part_name' => 'Tire - Rear (90/100-12)', 'part_code' => 'TYR-R90', 'category' => 'Tires', 'unit' => 'pcs', 'purchase_price' => 1550.00, 'mrp' => 1950.00, 'labor_charge' => 150.00, 'hsn_code' => '40111000', 'gst_rate' => 18.00, 'current_stock_qty' => 10, 'reorder_level' => 4],
            ['part_name' => 'Charger Port Assembly', 'part_code' => 'CHG-001', 'category' => 'Charging', 'unit' => 'pcs', 'purchase_price' => 1700.00, 'mrp' => 2200.00, 'labor_charge' => 200.00, 'hsn_code' => '85369010', 'gst_rate' => 18.00, 'current_stock_qty' => 7, 'reorder_level' => 3],
            ['part_name' => 'Seat Cover', 'part_code' => 'ST-001', 'category' => 'Body Parts', 'unit' => 'pcs', 'purchase_price' => 620.00, 'mrp' => 850.00, 'labor_charge' => 100.00, 'hsn_code' => '87089900', 'gst_rate' => 18.00, 'current_stock_qty' => 20, 'reorder_level' => 5],
            ['part_name' => 'Side Panel (Left)', 'part_code' => 'PNL-L01', 'category' => 'Body Parts', 'unit' => 'pcs', 'purchase_price' => 920.00, 'mrp' => 1200.00, 'labor_charge' => 150.00, 'hsn_code' => '87089900', 'gst_rate' => 18.00, 'current_stock_qty' => 8, 'reorder_level' => 3],
            ['part_name' => 'Side Panel (Right)', 'part_code' => 'PNL-R01', 'category' => 'Body Parts', 'unit' => 'pcs', 'purchase_price' => 920.00, 'mrp' => 1200.00, 'labor_charge' => 150.00, 'hsn_code' => '87089900', 'gst_rate' => 18.00, 'current_stock_qty' => 8, 'reorder_level' => 3],
            ['part_name' => 'Footrest Rubber', 'part_code' => 'FTR-001', 'category' => 'Body Parts', 'unit' => 'set', 'purchase_price' => 120.00, 'mrp' => 180.00, 'labor_charge' => 50.00, 'hsn_code' => '40169990', 'gst_rate' => 18.00, 'current_stock_qty' => 25, 'reorder_level' => 10],
            ['part_name' => 'Horn Assembly', 'part_code' => 'HRN-001', 'category' => 'Electrical', 'unit' => 'pcs', 'purchase_price' => 260.00, 'mrp' => 350.00, 'labor_charge' => 50.00, 'hsn_code' => '85129000', 'gst_rate' => 18.00, 'current_stock_qty' => 15, 'reorder_level' => 5],
        ];

        foreach ($parts as $part) {
            SparePart::firstOrCreate(
                ['part_code' => $part['part_code']],
                $part
            );
        }
    }

    private function seedLabourMasters(): void
    {
        $labours = [
            ['service_name' => 'General Service', 'category' => 'general', 'standard_rate' => 500.00, 'estimated_time_minutes' => 60, 'taxable' => true, 'gst_rate' => 18.00, 'hsn_sac_code' => '998714'],
            ['service_name' => 'Battery Health Check', 'category' => 'battery', 'standard_rate' => 300.00, 'estimated_time_minutes' => 30, 'taxable' => true, 'gst_rate' => 18.00, 'hsn_sac_code' => '998714'],
            ['service_name' => 'Motor Service', 'category' => 'motor', 'standard_rate' => 800.00, 'estimated_time_minutes' => 120, 'taxable' => true, 'gst_rate' => 18.00, 'hsn_sac_code' => '998714'],
            ['service_name' => 'Brake Adjustment', 'category' => 'brakes', 'standard_rate' => 200.00, 'estimated_time_minutes' => 30, 'taxable' => true, 'gst_rate' => 18.00, 'hsn_sac_code' => '998714'],
            ['service_name' => 'Tire Replacement', 'category' => 'general', 'standard_rate' => 150.00, 'estimated_time_minutes' => 20, 'taxable' => true, 'gst_rate' => 18.00, 'hsn_sac_code' => '998714'],
            ['service_name' => 'Controller Diagnosis', 'category' => 'electrical', 'standard_rate' => 600.00, 'estimated_time_minutes' => 90, 'taxable' => true, 'gst_rate' => 18.00, 'hsn_sac_code' => '998714'],
            ['service_name' => 'Display Panel Repair', 'category' => 'electrical', 'standard_rate' => 400.00, 'estimated_time_minutes' => 45, 'taxable' => true, 'gst_rate' => 18.00, 'hsn_sac_code' => '998714'],
            ['service_name' => 'Wiring Harness Check', 'category' => 'electrical', 'standard_rate' => 350.00, 'estimated_time_minutes' => 40, 'taxable' => true, 'gst_rate' => 18.00, 'hsn_sac_code' => '998714'],
            ['service_name' => 'Body Panel Replacement', 'category' => 'other', 'standard_rate' => 250.00, 'estimated_time_minutes' => 30, 'taxable' => true, 'gst_rate' => 18.00, 'hsn_sac_code' => '998714'],
            ['service_name' => 'Complete Overhaul', 'category' => 'general', 'standard_rate' => 2500.00, 'estimated_time_minutes' => 360, 'taxable' => true, 'gst_rate' => 18.00, 'hsn_sac_code' => '998714'],
        ];

        foreach ($labours as $labour) {
            LabourMaster::firstOrCreate(['service_name' => $labour['service_name']], $labour);
        }
    }

    private function seedCustomersAndVehicles(): void
    {
        // Get or create brands and models (should already exist from VehicleCatalogSeeder)
        $olaBrand = VehicleBrand::firstOrCreate(['name' => 'Ola']);
        $atherBrand = VehicleBrand::firstOrCreate(['name' => 'Ather']);
        $tvsBrand = VehicleBrand::firstOrCreate(['name' => 'TVS']);
        $bajajBrand = VehicleBrand::firstOrCreate(['name' => 'Bajaj']);

        $olaS1 = VehicleModel::firstOrCreate(
            ['vehicle_brand_id' => $olaBrand->id, 'name' => 'S1 Pro'],
            ['battery_type' => 'lithium-ion']
        );
        $ather450x = VehicleModel::firstOrCreate(
            ['vehicle_brand_id' => $atherBrand->id, 'name' => '450X'],
            ['battery_type' => 'lithium-ion']
        );
        $tvsIqube = VehicleModel::firstOrCreate(
            ['vehicle_brand_id' => $tvsBrand->id, 'name' => 'iQube'],
            ['battery_type' => 'lithium-ion']
        );
        $chetak = VehicleModel::firstOrCreate(
            ['vehicle_brand_id' => $bajajBrand->id, 'name' => 'Chetak'],
            ['battery_type' => 'lithium-ion']
        );

        // Regular customers with vehicles
        $customers = [
            [
                'customer' => [
                    'first_name' => 'Rahul',
                    'last_name' => 'Mehta',
                    'mobile' => '+91 98765 00001',
                    'email' => 'rahul.mehta@example.com',
                    'address' => 'A-101, Green Park Society, Vesu',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $olaS1->id, 'registration_no' => 'GJ-05-AB-1234', 'color' => 'Black', 'is_non_rto' => false],
                    ['model_id' => $ather450x->id, 'registration_no' => 'GJ-05-CD-5678', 'color' => 'Blue', 'is_non_rto' => false],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Priya',
                    'last_name' => 'Shah',
                    'mobile' => '+91 98765 00002',
                    'email' => 'priya.shah@example.com',
                    'address' => 'B-205, Sunrise Apartments, Adajan',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $tvsIqube->id, 'registration_no' => 'GJ-05-EF-9012', 'color' => 'White', 'is_non_rto' => false],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Amit',
                    'last_name' => 'Patel',
                    'mobile' => '+91 98765 00003',
                    'email' => null,
                    'address' => 'C-15, Old City Area, Rander Road',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $chetak->id, 'registration_no' => null, 'color' => 'Red', 'is_non_rto' => true], // Non-RTO vehicle
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Neha',
                    'last_name' => 'Desai',
                    'mobile' => '+91 98765 00004',
                    'email' => 'neha.desai@example.com',
                    'address' => 'D-302, City Heights, Piplod',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => null,
                ],
                'vehicles' => [
                    ['model_id' => $olaS1->id, 'registration_no' => 'GJ-05-GH-3456', 'color' => 'Silver', 'is_non_rto' => false],
                ],
            ],
            [
                'customer' => [
                    'first_name' => 'Vijay',
                    'last_name' => 'Singh',
                    'mobile' => '+91 98765 00005',
                    'email' => 'vijay.singh@example.com',
                    'address' => 'E-12, Shanti Nagar, Katargam',
                    'city' => 'Surat',
                    'customer_type' => 'regular',
                    'gstin' => '24ABCDE5678F1Z9',
                ],
                'vehicles' => [
                    ['model_id' => $ather450x->id, 'registration_no' => 'GJ-05-IJ-7890', 'color' => 'Grey', 'is_non_rto' => false],
                    ['model_id' => $tvsIqube->id, 'registration_no' => 'GJ-05-KL-2345', 'color' => 'Black', 'is_non_rto' => false],
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

        // Counter customers (parts-only, may not have vehicles)
        $counterCustomers = [
            [
                'company_name' => 'Karan Motors',
                'mobile' => '+91 98765 00006',
                'email' => 'karan.motors@example.com',
                'address' => 'Shop 5, Industrial Estate, Udhna',
                'city' => 'Surat',
                'customer_type' => 'counter',
                'gstin' => '24KLMNO1234P1Q2',
            ],
            [
                'first_name' => 'Walk-in',
                'last_name' => 'Customer',
                'mobile' => '+91 98765 00007',
                'email' => null,
                'address' => null,
                'city' => null,
                'customer_type' => 'counter',
                'gstin' => null,
            ],
        ];

        foreach ($counterCustomers as $customerData) {
            Customer::firstOrCreate(
                ['mobile' => $customerData['mobile']],
                $customerData
            );
        }
    }

    private function seedJobCardsWithVariousStatuses(): void
    {
        $employees = Employee::all();
        $labours = LabourMaster::all();
        $parts = SparePart::all();

        if ($employees->isEmpty() || $labours->isEmpty() || $parts->isEmpty()) {
            $this->command->warn('Skipping job cards - missing required data');

            return;
        }

        // Scenario 1: Completed job card with parts and labour
        $customer1 = Customer::where('mobile', '+91 98765 00001')->first();
        if ($customer1 && $customer1->vehicles->isNotEmpty()) {
            $vehicle1 = $customer1->vehicles->first();

            $jobCard1 = JobCard::create([
                'job_card_no' => 'JC-'.now()->format('Ymd').'-001',
                'job_date' => now()->subDays(10),
                'customer_id' => $customer1->id,
                'vehicle_id' => $vehicle1->id,
                'odometer_reading_km' => 1250,
                'job_type' => 'paid_service',
                'assigned_technician_id' => $employees->random()->id,
                'status' => 'delivered',
                'gst_applicable' => true,
                'grand_total' => 0,
            ]);

            JobCardLabour::create([
                'job_card_id' => $jobCard1->id,
                'labour_master_id' => $labours->where('service_name', 'Battery Health Check')->first()->id,
                'rate' => 300.00,
                'qty' => 1,
                'line_total' => 300.00,
            ]);

            JobCardLabour::create([
                'job_card_id' => $jobCard1->id,
                'labour_master_id' => $labours->where('service_name', 'Brake Adjustment')->first()->id,
                'rate' => 200.00,
                'qty' => 1,
                'line_total' => 200.00,
            ]);

            $part1 = $parts->where('part_name', 'Front Brake Pad')->first();
            if ($part1) {
                JobCardPart::create([
                    'job_card_id' => $jobCard1->id,
                    'spare_part_id' => $part1->id,
                    'quantity' => 2,
                    'mrp' => $part1->mrp,
                    'labor_charge' => $part1->labor_charge,
                    'line_total' => ($part1->mrp + $part1->labor_charge) * 2,
                ]);
            }
        }

        // Scenario 2: In-progress job card
        $customer2 = Customer::where('mobile', '+91 98765 00002')->first();
        if ($customer2 && $customer2->vehicles->isNotEmpty()) {
            $vehicle2 = $customer2->vehicles->first();

            $jobCard2 = JobCard::create([
                'job_card_no' => 'JC-'.now()->format('Ymd').'-002',
                'job_date' => now()->subDays(2),
                'customer_id' => $customer2->id,
                'vehicle_id' => $vehicle2->id,
                'odometer_reading_km' => 2340,
                'job_type' => 'paid_service',
                'assigned_technician_id' => $employees->random()->id,
                'status' => 'in_progress',
                'gst_applicable' => true,
                'grand_total' => 0,
            ]);

            JobCardLabour::create([
                'job_card_id' => $jobCard2->id,
                'labour_master_id' => $labours->where('service_name', 'Display Panel Repair')->first()->id,
                'rate' => 400.00,
                'qty' => 1,
                'line_total' => 400.00,
            ]);

            $part2 = $parts->where('part_name', 'Display Screen')->first();
            if ($part2) {
                JobCardPart::create([
                    'job_card_id' => $jobCard2->id,
                    'spare_part_id' => $part2->id,
                    'quantity' => 1,
                    'mrp' => $part2->mrp,
                    'labor_charge' => $part2->labor_charge,
                    'line_total' => $part2->mrp + $part2->labor_charge,
                ]);
            }
        }

        // Scenario 3: Just received job card
        $customer3 = Customer::where('mobile', '+91 98765 00003')->first();
        if ($customer3 && $customer3->vehicles->isNotEmpty()) {
            $vehicle3 = $customer3->vehicles->first();

            JobCard::create([
                'job_card_no' => 'JC-'.now()->format('Ymd').'-003',
                'job_date' => now(),
                'customer_id' => $customer3->id,
                'vehicle_id' => $vehicle3->id,
                'odometer_reading_km' => 890,
                'job_type' => 'paid_service',
                'assigned_technician_id' => $employees->random()->id,
                'status' => 'received',
                'gst_applicable' => true,
                'grand_total' => 0,
            ]);
        }

        // Scenario 4: Waiting for parts
        $customer4 = Customer::where('mobile', '+91 98765 00004')->first();
        if ($customer4 && $customer4->vehicles->isNotEmpty()) {
            $vehicle4 = $customer4->vehicles->first();

            $jobCard4 = JobCard::create([
                'job_card_no' => 'JC-'.now()->format('Ymd').'-004',
                'job_date' => now()->subDays(3),
                'customer_id' => $customer4->id,
                'vehicle_id' => $vehicle4->id,
                'odometer_reading_km' => 3456,
                'job_type' => 'paid_service',
                'assigned_technician_id' => $employees->random()->id,
                'status' => 'waiting_for_parts',
                'gst_applicable' => true,
                'grand_total' => 0,
            ]);

            JobCardLabour::create([
                'job_card_id' => $jobCard4->id,
                'labour_master_id' => $labours->where('service_name', 'General Service')->first()->id,
                'rate' => 500.00,
                'qty' => 1,
                'line_total' => 500.00,
            ]);
        }

        // Scenario 5: Large completed job with multiple parts
        $customer5 = Customer::where('mobile', '+91 98765 00005')->first();
        if ($customer5 && $customer5->vehicles->isNotEmpty()) {
            $vehicle5 = $customer5->vehicles->first();

            $jobCard5 = JobCard::create([
                'job_card_no' => 'JC-'.now()->format('Ymd').'-005',
                'job_date' => now()->subDays(15),
                'customer_id' => $customer5->id,
                'vehicle_id' => $vehicle5->id,
                'odometer_reading_km' => 5670,
                'job_type' => 'paid_service',
                'assigned_technician_id' => $employees->random()->id,
                'supervisor_id' => $employees->random()->id,
                'status' => 'delivered',
                'gst_applicable' => true,
                'discount_amount' => 500.00,
                'grand_total' => 0,
            ]);

            JobCardLabour::create([
                'job_card_id' => $jobCard5->id,
                'labour_master_id' => $labours->where('service_name', 'Complete Overhaul')->first()->id,
                'rate' => 2500.00,
                'qty' => 1,
                'line_total' => 2500.00,
            ]);

            JobCardLabour::create([
                'job_card_id' => $jobCard5->id,
                'labour_master_id' => $labours->where('service_name', 'Body Panel Replacement')->first()->id,
                'rate' => 250.00,
                'qty' => 4,
                'line_total' => 1000.00,
            ]);

            $multiParts = [
                'Side Panel (Left)' => 1,
                'Side Panel (Right)' => 1,
                'Front Brake Pad' => 2,
                'Rear Brake Pad' => 2,
                'LED Headlight Assembly' => 1,
            ];

            foreach ($multiParts as $partName => $qty) {
                $part = $parts->where('part_name', $partName)->first();
                if ($part) {
                    JobCardPart::create([
                        'job_card_id' => $jobCard5->id,
                        'spare_part_id' => $part->id,
                        'quantity' => $qty,
                        'mrp' => $part->mrp,
                        'labor_charge' => $part->labor_charge,
                        'line_total' => ($part->mrp + $part->labor_charge) * $qty,
                    ]);
                }
            }
        }
    }

    private function seedCounterInvoices(): void
    {
        $parts = SparePart::all();

        if ($parts->isEmpty()) {
            $this->command->warn('Skipping counter invoices - no spare parts available');

            return;
        }

        // Counter Invoice 1: Walk-in customer, parts only
        $counterCustomer1 = Customer::where('mobile', '+91 98765 00007')->first();
        if ($counterCustomer1) {
            $counterInvoice1 = CounterInvoice::create([
                'invoice_no' => 'CI-'.now()->format('Ymd').'-001',
                'customer_id' => $counterCustomer1->id,
                'invoice_date' => now()->subDays(7),
                'gst_applicable' => true,
                'grand_total' => 0,
            ]);

            $fuse = $parts->where('part_name', 'Battery Fuse 30A')->first();
            if ($fuse) {
                CounterInvoiceItem::create([
                    'counter_invoice_id' => $counterInvoice1->id,
                    'spare_part_id' => $fuse->id,
                    'quantity' => 5,
                    'mrp' => $fuse->mrp,
                    'line_total' => $fuse->mrp * 5,
                ]);
            }

            $footrest = $parts->where('part_name', 'Footrest Rubber')->first();
            if ($footrest) {
                CounterInvoiceItem::create([
                    'counter_invoice_id' => $counterInvoice1->id,
                    'spare_part_id' => $footrest->id,
                    'quantity' => 2,
                    'mrp' => $footrest->mrp,
                    'line_total' => $footrest->mrp * 2,
                ]);
            }
        }

        // Counter Invoice 2: Business customer with GSTIN
        $counterCustomer2 = Customer::where('mobile', '+91 98765 00006')->first();
        if ($counterCustomer2) {
            $counterInvoice2 = CounterInvoice::create([
                'invoice_no' => 'CI-'.now()->format('Ymd').'-002',
                'customer_id' => $counterCustomer2->id,
                'invoice_date' => now()->subDays(4),
                'gst_applicable' => true,
                'discount_amount' => 200.00,
                'grand_total' => 0,
            ]);

            $bms = $parts->where('part_name', 'Battery BMS Unit')->first();
            if ($bms) {
                CounterInvoiceItem::create([
                    'counter_invoice_id' => $counterInvoice2->id,
                    'spare_part_id' => $bms->id,
                    'quantity' => 2,
                    'mrp' => $bms->mrp,
                    'line_total' => $bms->mrp * 2,
                ]);
            }

            $pcb = $parts->where('part_name', 'Motor Controller PCB')->first();
            if ($pcb) {
                CounterInvoiceItem::create([
                    'counter_invoice_id' => $counterInvoice2->id,
                    'spare_part_id' => $pcb->id,
                    'quantity' => 1,
                    'mrp' => $pcb->mrp,
                    'line_total' => $pcb->mrp,
                ]);
            }
        }

        // Counter Invoice 3: Recent invoice
        if ($counterCustomer1) {
            $counterInvoice3 = CounterInvoice::create([
                'invoice_no' => 'CI-'.now()->format('Ymd').'-003',
                'customer_id' => $counterCustomer1->id,
                'invoice_date' => now()->subDays(1),
                'gst_applicable' => true,
                'grand_total' => 0,
            ]);

            $horn = $parts->where('part_name', 'Horn Assembly')->first();
            if ($horn) {
                CounterInvoiceItem::create([
                    'counter_invoice_id' => $counterInvoice3->id,
                    'spare_part_id' => $horn->id,
                    'quantity' => 3,
                    'mrp' => $horn->mrp,
                    'line_total' => $horn->mrp * 3,
                ]);
            }
        }
    }

    private function seedPayments(): void
    {
        // Simplified - payments will be added through the UI
        // This keeps the seeder focused on core master data
        $this->command->info('Payments can be added through the application UI');
    }
}
