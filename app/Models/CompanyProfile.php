<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    protected $table = 'company_profile';

    protected $fillable = [
        'company_name',
        'address',
        'city',
        'state',
        'pincode',
        'phone',
        'email',
        'gstin',
        'bank_name',
        'bank_account_no',
        'bank_ifsc',
        'terms_and_conditions',
        'logo_path',
    ];

    /**
     * Get the single company profile row, creating the default placeholder
     * if it does not exist yet. Guarantees the app always has exactly one.
     */
    public static function profile(): self
    {
        return static::query()->firstOrCreate(
            [],
            ['company_name' => config('garage.company.name')]
        );
    }
}
