<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->unsignedBigInteger('toyota_source_id')->nullable()->unique();
            $table->string('website', 2048)->nullable();
            $table->string('facebook_url', 2048)->nullable();
            $table->string('zalo_url', 2048)->nullable();
            $table->text('opening_hours')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropUnique(['toyota_source_id']);
            $table->dropColumn(['toyota_source_id', 'website', 'facebook_url', 'zalo_url', 'opening_hours']);
        });
    }
};
