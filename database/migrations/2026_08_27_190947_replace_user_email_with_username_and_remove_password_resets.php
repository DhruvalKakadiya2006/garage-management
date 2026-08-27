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
        if (Schema::hasTable('password_reset_tokens')) {
            Schema::drop('password_reset_tokens');
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }

            if (Schema::hasColumn('users', 'email')) {
                $table->dropUnique(['email']);
                $table->dropColumn('email');
            }

            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'username')) {
                $table->dropUnique(['username']);
                $table->dropColumn('username');
            }

            if (! Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->unique()->after('name');
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
