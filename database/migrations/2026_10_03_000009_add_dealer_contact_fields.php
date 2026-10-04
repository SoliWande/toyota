<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->string('province')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
        });

        Schema::table('award_winners', function (Blueprint $table) {
            $table->foreign('sales_dealer_id_snapshot')->references('id')->on('dealers');
        });
    }

    public function down(): void
    {
        Schema::table('award_winners', fn (Blueprint $table) => $table->dropForeign(['sales_dealer_id_snapshot']));
        Schema::table('dealers', fn (Blueprint $table) => $table->dropColumn(['province', 'phone', 'address']));
    }
};
