<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Single source of truth for the rupee symbol used across every form,
    | label and invoice view. Change it here instead of editing views.
    |
    */

    'currency_symbol' => '₹',

    /*
    |--------------------------------------------------------------------------
    | Workshop / company details
    |--------------------------------------------------------------------------
    |
    | Printed on the header of Job Card invoices and estimates. Values come
    | from the .env file so they can be changed without touching code; empty
    | entries are simply omitted from the document.
    |
    */

    'company' => [
        'name' => env('COMPANY_NAME', 'Kakadiya EV Hub'),
        'address_line1' => env('COMPANY_ADDRESS_LINE1', ''),
        'address_line2' => env('COMPANY_ADDRESS_LINE2', ''),
        'city' => env('COMPANY_CITY', 'Surat'),
        'state' => env('COMPANY_STATE', 'Gujarat'),
        'pin' => env('COMPANY_PIN', ''),
        'gstin' => env('COMPANY_GSTIN', ''),
        'phone' => env('COMPANY_PHONE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Estimate validity
    |--------------------------------------------------------------------------
    |
    | Number of days an estimate remains valid. Shown on the Estimate PDF in
    | place of invoice payment terms.
    |
    */

    'estimate_validity_days' => (int) env('ESTIMATE_VALIDITY_DAYS', 15),

    'pagination' => [
        'per_page' => (int) env('PAGINATION_PER_PAGE', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Option lists
    |--------------------------------------------------------------------------
    |
    | Single source of truth for fixed dropdown options. Both controller
    | validation rules and Blade <select> options reference these arrays so
    | the allowed values can never drift apart.
    |
    */

    'option_lists' => [

        'customer_type' => [
            'regular' => 'Regular',
            'counter' => 'Counter',
        ],

        'job_type' => [
            'free_service' => 'Free Service',
            'paid_service' => 'Paid Service',
            'warranty' => 'Warranty',
            'amc' => 'AMC',
            'insurance_claim' => 'Insurance Claim',
            'breakdown' => 'Breakdown',
        ],

        'job_card_status' => [
            'received' => 'Received',
            'in_progress' => 'In Progress',
            'waiting_for_parts' => 'Waiting For Parts',
            'ready_for_delivery' => 'Ready For Delivery',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ],

        'payment_mode' => [
            'cash' => 'Cash',
            'upi' => 'UPI',
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
        ],

        'employee_role' => [
            'technician' => 'Technician',
            'supervisor' => 'Supervisor',
            'helper' => 'Helper',
            'front_desk' => 'Front Desk',
            'admin' => 'Admin',
        ],

        'employee_specialization' => [
            'battery' => 'Battery',
            'motor_controller' => 'Motor Controller',
            'electrical' => 'Electrical',
            'general' => 'General',
        ],

        'vehicle_battery_type' => [
            'lithium-ion' => 'Lithium-ion',
            'lead-acid' => 'Lead-acid',
            'other' => 'Other',
        ],

        'spare_part_unit' => [
            'pcs' => 'Pcs',
            'set' => 'Set',
            'meter' => 'Meter',
            'kg' => 'Kg',
        ],

        'labour_master_category' => [
            'battery' => 'Battery',
            'motor' => 'Motor',
            'brakes' => 'Brakes',
            'electrical' => 'Electrical',
            'general' => 'General',
            'other' => 'Other',
        ],

    ],

];
