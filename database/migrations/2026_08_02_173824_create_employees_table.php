<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('role', ['technician', 'supervisor', 'helper', 'front_desk', 'admin']);
            $table->string('mobile');
            $table->enum('specialization', ['battery', 'motor_controller', 'electrical', 'general'])->nullable();
            $table->boolean('active')->default(true);
            $table->date('join_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
