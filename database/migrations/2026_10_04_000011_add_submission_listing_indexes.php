<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_submissions', function (Blueprint $table) {
            // A status between sales_id and submitted_at cannot order an unfiltered Sales list.
            $table->index(['sales_id', 'submitted_at'], 'submissions_sales_time_index');
            $table->index('submitted_at', 'submissions_time_index');
        });
    }

    public function down(): void
    {
        Schema::table('customer_submissions', function (Blueprint $table) {
            $table->dropIndex('submissions_sales_time_index');
            $table->dropIndex('submissions_time_index');
        });
    }
};
