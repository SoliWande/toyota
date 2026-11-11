<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_audit_batches', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('purpose', 32)->charset('ascii')->collation('ascii_bin');
            $table->string('period_type', 16)->charset('ascii')->collation('ascii_bin')->default('monthly');
            $table->string('monthly_scope_key', 7)->charset('ascii')->collation('ascii_bin');
            $table->dateTime('period_start', 6);
            $table->dateTime('period_end', 6);
            $table->string('calendar_zone', 64);
            $table->string('calendar_version', 64);
            $table->unsignedInteger('generation')->default(1);
            $table->foreignId('predecessor_batch_id')->nullable()->constrained('call_audit_batches')->restrictOnDelete();
            // Default NO ACTION is restrictive, without a referential-action clause on a CHECK column.
            $table->foreignId('award_id')->nullable()->constrained('awards');
            $table->dateTime('approval_cutoff_at', 6);
            $table->string('cutoff_policy_version', 64);
            $table->dateTime('eligibility_as_of', 6);
            $table->dateTime('frozen_at', 6);
            $table->longText('review_completion_evidence_encrypted');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('frozen_by')->constrained('users')->restrictOnDelete();
            foreach (['policy_version', 'manifest_version', 'algorithm_version', 'fingerprint_version'] as $column) {
                $table->string($column, 64);
            }
            $table->unsignedInteger('eligible_count');
            $table->unsignedInteger('requested_count');
            $table->unsignedInteger('actual_count');
            $table->unsignedInteger('target_cap');
            $table->longText('strata_snapshot_encrypted');
            $table->longText('award_baseline_encrypted')->nullable();
            $table->char('award_baseline_fingerprint', 64)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->longText('manifest_encrypted');
            $table->char('manifest_digest', 64)->charset('ascii')->collation('ascii_bin');
            $table->char('selection_digest', 64)->charset('ascii')->collation('ascii_bin');
            $table->longText('seed_encrypted');
            $table->string('encryption_key_id', 64);
            $table->string('encryption_version', 64);
            $table->string('digest_key_id', 64);
            $table->json('demo_policy_snapshot');
            $table->string('lifecycle_state', 32)->default('frozen');
            $table->unsignedInteger('row_version')->default(1);
            $table->string('create_request_key', 64)->charset('ascii')->collation('ascii_bin');
            $table->char('create_payload_digest', 64)->charset('ascii')->collation('ascii_bin');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6)->nullable();

            $table->unique(['purpose', 'monthly_scope_key', 'generation'], 'cab_scope_generation_uq');
            $table->unique(['created_by', 'create_request_key'], 'cab_create_request_uq');
            $table->index(['purpose', 'monthly_scope_key', 'lifecycle_state'], 'cab_scope_state_idx');
        });

        DB::statement("ALTER TABLE call_audit_batches ADD CONSTRAINT cab_purpose_ck CHECK ((purpose = 'general_audit' AND award_id IS NULL AND award_baseline_encrypted IS NULL AND award_baseline_fingerprint IS NULL AND requested_count = 60 AND target_cap = 60) OR (purpose = 'award_top3' AND award_id IS NOT NULL AND award_baseline_encrypted IS NOT NULL AND award_baseline_fingerprint IS NOT NULL AND requested_count <= 15 AND MOD(requested_count, 5) = 0 AND target_cap = 15))");
        DB::statement("ALTER TABLE call_audit_batches ADD CONSTRAINT cab_period_ck CHECK (period_type = 'monthly' AND monthly_scope_key REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$' AND period_end > period_start AND frozen_at >= period_end AND frozen_at >= approval_cutoff_at AND eligibility_as_of <= frozen_at)");
        DB::statement('ALTER TABLE call_audit_batches ADD CONSTRAINT cab_counts_ck CHECK (actual_count <= requested_count AND actual_count <= eligible_count AND generation > 0 AND row_version > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('call_audit_batches');
    }
};
