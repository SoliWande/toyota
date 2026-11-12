<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_penalty_evidence', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('entry_id')->constrained('dealer_penalty_entries')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('call_audit_batches')->restrictOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('attempt_id');
            $table->foreignId('call_event_id')->constrained('call_audit_events')->restrictOnDelete();
            $table->foreignId('resolution_event_id')->constrained('call_audit_events')->restrictOnDelete();
            $table->unsignedInteger('resolution_version');
            $table->unsignedInteger('item_version_used');
            $table->string('schema_version', 64);
            $table->string('verification_version', 64);
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('call_recorded_at', 6);
            $table->dateTime('resolution_recorded_at', 6);
            $table->dateTime('verified_at', 6);
            $table->longText('evidence_snapshot_encrypted');
            $table->char('evidence_digest', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('digest_key_id', 64);
            $table->string('encryption_version', 64);
            $table->string('encryption_key_id', 64);
            $table->unique('resolution_event_id', 'dpv_resolution_event_uq');
            $table->unique(['entry_id', 'item_id', 'resolution_version'], 'dpv_entry_item_version_uq');
            $table->unique(['id', 'entry_id'], 'dpv_id_entry_uq');
            $table->foreign(['item_id', 'batch_id'], 'dpv_item_batch_fk')->references(['id', 'batch_id'])->on('call_audit_items')->restrictOnDelete();
            $table->foreign(['attempt_id', 'item_id'], 'dpv_attempt_item_fk')->references(['id', 'item_id'])->on('call_audit_attempts')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE dealer_penalty_evidence ADD CONSTRAINT dpv_versions_ck CHECK (resolution_version > 0 AND item_version_used > 0)');
        DB::statement('ALTER TABLE dealer_penalty_evidence ADD CONSTRAINT dpv_time_ck CHECK (resolution_recorded_at >= call_recorded_at AND verified_at >= resolution_recorded_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_penalty_evidence');
    }
};
