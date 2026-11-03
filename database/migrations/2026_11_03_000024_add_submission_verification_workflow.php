<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_verification_states', function (Blueprint $table) {
            $table->foreignId('submission_id')->primary()->constrained('customer_submissions')->restrictOnDelete();
            $table->string('group_progress', 24)->nullable();
            $table->foreignId('group_verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('group_verified_at')->nullable();
            $table->string('blocker_code', 40)->nullable();
            $table->foreignId('blocker_updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('blocker_updated_at')->nullable();
            $table->uuid('supplement_key')->nullable()->unique();
            $table->string('supplement_state', 24)->nullable();
            $table->string('supplement_reason', 40)->nullable();
            $table->foreignId('supplement_requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('supplement_requested_at')->nullable();
            $table->timestamp('supplement_responded_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
        Schema::create('submission_verification_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('customer_submissions')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_type', 16);
            $table->string('action', 32);
            $table->json('old_values');
            $table->json('new_values');
            $table->timestamp('created_at');
            $table->index(['submission_id', 'id']);
        });
        // No backfill: absence of a row/null progress means unknown.
    }

    public function down(): void
    {
        if (DB::table('submission_verification_states')->exists() || DB::table('submission_verification_audits')->exists()) {
            throw new RuntimeException('Verification history must be retained; rollback requires an explicit retention plan.');
        }
        Schema::dropIfExists('submission_verification_audits');
        Schema::dropIfExists('submission_verification_states');
    }
};
