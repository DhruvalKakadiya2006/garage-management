<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            $table->index('part_name');
            $table->index('part_code');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('spare_parts', function (Blueprint $table) {
            $table->dropIndex(['part_name']);
            $table->dropIndex(['part_code']);
            $table->dropIndex(['category']);
        });
    }
};
