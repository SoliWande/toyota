<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('users');
            $table->string('customer_name');
            $table->string('facebook_url', 2048);
            $table->string('facebook_url_normalized', 255)->collation('utf8mb4_bin');
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('submitted_at');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('approved_facebook_identity', 255)
                ->collation('utf8mb4_bin')
                ->storedAs("CASE WHEN status = 'approved' THEN facebook_url_normalized ELSE NULL END");
            $table->unique('approved_facebook_identity', 'submissions_approved_profile_unique');
            $table->index('facebook_url_normalized', 'submissions_profile_index');
            $table->index(['status', 'submitted_at'], 'submissions_status_time_index');
            $table->index(['sales_id', 'status', 'submitted_at'], 'submissions_sales_status_time_index');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE customer_submissions ADD CONSTRAINT submissions_review_check CHECK ((status = 'pending' AND reviewed_by IS NULL AND reviewed_at IS NULL AND rejection_reason IS NULL AND admin_note IS NULL) OR (status IN ('approved', 'rejected') AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL))");
        DB::statement('ALTER TABLE customer_submissions ADD CONSTRAINT submissions_profile_check CHECK (CHAR_LENGTH(facebook_url_normalized) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_submissions');
    }
};
