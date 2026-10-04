<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_submissions', function (Blueprint $table) {
            // Nullable preserves historical records; the request requires all new fields.
            $table->string('vehicle_model', 80)->nullable();
            $table->unsignedSmallInteger('first_registration_year')->nullable();
            $table->string('vehicle_color', 50)->nullable();
            $table->string('license_plate', 20)->nullable();
            $table->string('evidence_image_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_submissions', function (Blueprint $table) {
            $table->dropColumn(['vehicle_model', 'first_registration_year', 'vehicle_color', 'license_plate', 'evidence_image_path']);
        });
    }
};
