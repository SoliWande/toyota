<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_submissions', function (Blueprint $table) {
            $table->unsignedInteger('odo')->nullable();
        });
        Schema::create('submission_intake_mutex', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('submission_intake_mutex')->insert(['id' => 1]);
        Schema::create('dealer_registration_receipts', function (Blueprint $table) {
            $table->char('draft_key', 64)->primary();
            $table->char('owner_key', 64)->index();
            $table->foreignId('submission_id')->constrained('customer_submissions')->restrictOnDelete();
            $table->text('confirmation'); // Encrypted, never a public record identifier.
            $table->dateTime('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (DB::table('dealer_registration_receipts')->exists() || DB::table('customer_submissions')->whereNotNull('odo')->exists()) {
            throw new RuntimeException('Final registration data exists; refusing a lossy rollback.');
        }
        Schema::dropIfExists('dealer_registration_receipts');
        Schema::dropIfExists('submission_intake_mutex');
        Schema::table('customer_submissions', fn (Blueprint $table) => $table->dropColumn('odo'));
    }
};
