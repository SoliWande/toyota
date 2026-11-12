<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_penalty_entries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('submission_id')->constrained('customer_submissions')->restrictOnDelete();
            $table->char('monthly_scope_key', 7)->charset('ascii')->collation('ascii_bin');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->string('calendar_zone', 64);
            $table->string('calendar_version', 64);
            $table->foreignId('dealer_id')->constrained('dealers')->restrictOnDelete();
            $table->unsignedInteger('points');
            $table->string('schema_version', 64);
            $table->string('policy_version', 64);
            $table->string('projection_policy_version', 64);
            $table->string('ranking_policy_version', 64);
            $table->longText('policy_snapshot_encrypted');
            $table->longText('dealer_attribution_encrypted');
            $table->string('attribution_version', 64);
            $table->dateTime('attribution_observed_at', 6);
            $table->dateTime('source_submitted_at');
            $table->char('source_fingerprint', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('fingerprint_version', 64);
            $table->foreignId('applied_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('applied_at', 6);
            $table->dateTime('effective_at', 6);
            $table->dateTime('created_at', 6);
            $table->string('encryption_version', 64);
            $table->string('encryption_key_id', 64);
            $table->unique(['submission_id', 'monthly_scope_key'], 'dpe_submission_month_uq');
            $table->index(['monthly_scope_key', 'dealer_id'], 'dpe_month_dealer_idx');
            $table->index(['applied_by', 'applied_at'], 'dpe_actor_time_idx');
        });
        DB::statement('ALTER TABLE dealer_penalty_entries ADD CONSTRAINT dpe_points_ck CHECK (points > 0)');
        DB::statement("ALTER TABLE dealer_penalty_entries ADD CONSTRAINT dpe_month_ck CHECK (monthly_scope_key REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$')");
        DB::statement('ALTER TABLE dealer_penalty_entries ADD CONSTRAINT dpe_period_ck CHECK (period_end > period_start AND source_submitted_at >= period_start AND source_submitted_at < period_end)');
        DB::statement('ALTER TABLE dealer_penalty_entries ADD CONSTRAINT dpe_time_ck CHECK (applied_at >= attribution_observed_at AND created_at >= applied_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_penalty_entries');
    }
};
