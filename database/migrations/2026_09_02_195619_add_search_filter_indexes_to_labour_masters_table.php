<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labour_masters', function (Blueprint $table) {
            $table->index('service_name');
            $table->index('category');
            $table->index('taxable');
        });
    }

    public function down(): void
    {
        Schema::table('labour_masters', function (Blueprint $table) {
            $table->dropIndex(['service_name']);
            $table->dropIndex(['category']);
            $table->dropIndex(['taxable']);
        });
    }
};
