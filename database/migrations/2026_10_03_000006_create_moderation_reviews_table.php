<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->nullable()->constrained('users');
            $table->foreignId('customer_submission_id')->nullable()->constrained();
            $table->foreignId('reviewed_by')->constrained('users');
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->text('rejection_reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->dateTime('reviewed_at');
            $table->index(['sales_id', 'reviewed_at']);
            $table->index(['customer_submission_id', 'reviewed_at'], 'reviews_submission_time_index');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE moderation_reviews ADD CONSTRAINT reviews_subject_status_check CHECK ((sales_id IS NOT NULL AND customer_submission_id IS NULL AND from_status IN ('pending', 'active', 'rejected', 'blocked') AND to_status IN ('pending', 'active', 'rejected', 'blocked')) OR (customer_submission_id IS NOT NULL AND sales_id IS NULL AND from_status IN ('pending', 'approved', 'rejected') AND to_status IN ('pending', 'approved', 'rejected')))");
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_reviews');
    }
};
