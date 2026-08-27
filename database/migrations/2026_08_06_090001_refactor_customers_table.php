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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name')->default('')->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('surname')->nullable()->after('last_name');
            $table->string('company_name')->nullable()->after('surname');
            $table->string('display_name')->default('')->after('company_name');
        });

        DB::statement('UPDATE customers SET first_name = name, display_name = name');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('pincode');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('city')->nullable()->default('Surat')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('name')->default('')->after('id');
            $table->string('pincode')->nullable();
        });

        DB::statement('UPDATE customers SET name = display_name');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('first_name');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('last_name');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('surname');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('company_name');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('city')->nullable()->default(null)->change();
        });
    }
};
