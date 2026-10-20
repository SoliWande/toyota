<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_submissions', function (Blueprint $table) {
            // Preserve historical records without inventing an address for them.
            $table->string('address_line', 500)->nullable();
            $table->string('province_code', 2)->nullable();
            $table->string('province_name')->nullable();
            $table->string('ward_code', 5)->nullable();
            $table->string('ward_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_submissions', function (Blueprint $table) {
            $table->dropColumn(['address_line', 'province_code', 'province_name', 'ward_code', 'ward_name']);
        });
    }
};
