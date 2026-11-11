<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_audit_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('batch_id')->constrained('call_audit_batches')->restrictOnDelete();
            $table->foreignId('submission_id')->constrained('customer_submissions')->restrictOnDelete();
            $table->unsignedInteger('selection_order');
            $table->string('stratum_key', 64)->charset('ascii')->collation('ascii_bin');
            $table->foreignId('dealer_id_snapshot')->constrained('dealers')->restrictOnDelete();
            $table->foreignId('sales_id_snapshot')->nullable()->constrained('users')->restrictOnDelete();
            $table->longText('dealer_snapshot_encrypted');
            $table->longText('source_snapshot_encrypted');
            $table->char('source_fingerprint', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('fingerprint_version', 64);
            $table->dateTime('source_observed_at', 6);
            $table->string('queue_state', 32)->default('uncalled');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('row_version')->default(1);
            $table->string('latest_outcome', 32)->nullable();
            $table->dateTime('retry_due_at', 6)->nullable();
            $table->foreignId('claim_owner_id')->nullable()->constrained('users');
            $table->char('claim_token_hash', 64)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->dateTime('claim_expires_at', 6)->nullable();
            $table->dateTime('source_stale_at', 6)->nullable();
            $table->char('source_drift_digest', 64)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['batch_id', 'submission_id'], 'cai_batch_submission_uq');
            $table->unique(['batch_id', 'selection_order'], 'cai_batch_order_uq');
            $table->unique(['id', 'batch_id'], 'cai_id_batch_uq');
            $table->index(['batch_id', 'queue_state', 'retry_due_at', 'selection_order'], 'cai_queue_idx');
            $table->index('claim_expires_at', 'cai_claim_expiry_idx');
        });

        DB::statement('ALTER TABLE call_audit_items ADD CONSTRAINT cai_version_order_ck CHECK (selection_order > 0 AND row_version > 0)');
        DB::statement('ALTER TABLE call_audit_items ADD CONSTRAINT cai_claim_ck CHECK ((claim_owner_id IS NULL AND claim_token_hash IS NULL AND claim_expires_at IS NULL) OR (claim_owner_id IS NOT NULL AND claim_token_hash IS NOT NULL AND claim_expires_at IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('call_audit_items');
    }
};
