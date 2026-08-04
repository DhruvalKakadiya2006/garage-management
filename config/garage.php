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
            'walk-in' => 'Walk-in',
            'regular' => 'Regular',
            'fleet' => 'Fleet',
            'dealer' => 'Dealer',
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

        'estimate_status' => [
            'draft' => 'Draft',
            'sent' => 'Sent',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'expired' => 'Expired',
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
