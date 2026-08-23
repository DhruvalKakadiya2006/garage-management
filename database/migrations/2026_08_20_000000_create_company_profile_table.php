<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_profile', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->string('pincode');
            $table->string('phone');
            $table->string('email');
            $table->string('gstin');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        // Seed exactly one default row so the app never has to handle a
        // "no company profile yet" null case. Values pre-fill from the
        // existing env-based company config as a starting point.
        DB::table('company_profile')->insert([
            'company_name' => config('garage.company.name'),
            'address' => '',
            'city' => config('garage.company.city'),
            'state' => config('garage.company.state'),
            'pincode' => '',
            'phone' => config('garage.company.phone'),
            'email' => '',
            'gstin' => config('garage.company.gstin'),
            'bank_name' => null,
            'bank_account_no' => null,
            'bank_ifsc' => null,
            'terms_and_conditions' => null,
            'logo_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profile');
    }
};
