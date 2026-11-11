<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_audit_attempts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('item_id')->constrained('call_audit_items')->restrictOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('started_at', 6);
            $table->dateTime('recorded_at', 6);
            $table->string('questionnaire_version', 64);
            $table->string('outcome', 32);
            $table->boolean('answer_1')->nullable();
            $table->boolean('answer_2')->nullable();
            $table->boolean('answer_3')->nullable();
            $table->longText('note_encrypted')->nullable();
            $table->char('source_fingerprint_used', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('item_version_used');
            $table->dateTime('retry_due_at', 6)->nullable();
            $table->string('retry_policy_version', 64)->nullable();
            $table->string('idempotency_key', 64)->charset('ascii')->collation('ascii_bin');
            $table->char('payload_digest', 64)->charset('ascii')->collation('ascii_bin');

            $table->unique(['item_id', 'attempt_no'], 'caa_item_number_uq');
            $table->unique(['item_id', 'actor_id', 'idempotency_key'], 'caa_request_uq');
            $table->unique(['id', 'item_id'], 'caa_id_item_uq');
            $table->index(['item_id', 'recorded_at'], 'caa_item_recorded_idx');
        });

        DB::statement('ALTER TABLE call_audit_attempts ADD CONSTRAINT caa_version_time_ck CHECK (attempt_no > 0 AND item_version_used > 0 AND recorded_at >= started_at)');
        DB::statement('ALTER TABLE call_audit_attempts ADD CONSTRAINT caa_retry_ck CHECK ((retry_due_at IS NULL AND retry_policy_version IS NULL) OR (retry_due_at IS NOT NULL AND retry_policy_version IS NOT NULL))');
        DB::statement('ALTER TABLE call_audit_attempts ADD CONSTRAINT caa_answers_ck CHECK ((answer_1 IS NULL OR answer_1 IN (0, 1)) AND (answer_2 IS NULL OR answer_2 IN (0, 1)) AND (answer_3 IS NULL OR answer_3 IN (0, 1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('call_audit_attempts');
    }
};
