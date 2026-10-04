<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_submissions', function (Blueprint $table) {
            // Legacy attribution is unknown: never infer it from the current sales dealer.
            $table->foreignId('dealer_id')->nullable()->after('sales_id')->constrained()->restrictOnDelete();
            $table->index(['dealer_id', 'status', 'submitted_at'], 'submissions_dealer_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('customer_submissions', function (Blueprint $table) {
            $table->dropForeign(['dealer_id']);
            $table->dropIndex('submissions_dealer_period_index');
            $table->dropColumn('dealer_id');
        });
    }
};
