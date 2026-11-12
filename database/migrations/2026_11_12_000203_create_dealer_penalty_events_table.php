<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_penalty_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('entry_id')->constrained('dealer_penalty_entries')->restrictOnDelete();
            $table->unsignedBigInteger('evidence_id')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('event_sequence');
            $table->string('event_type', 64);
            $table->string('event_schema_version', 64);
            $table->string('command_name', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('command_key', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('event_ordinal');
            $table->unsignedInteger('before_version');
            $table->unsignedInteger('after_version');
            $table->unsignedInteger('evidence_count_before');
            $table->unsignedInteger('evidence_count_after');
            $table->string('reason_code', 64);
            $table->string('policy_version', 64);
            $table->dateTime('occurred_at', 6);
            $table->string('correlation_id', 64)->charset('ascii')->collation('ascii_bin');
            $table->json('metadata');
            $table->longText('intent_encrypted');
            $table->longText('receipt_encrypted');
            $table->char('payload_digest', 64)->charset('ascii')->collation('ascii_bin');
            $table->char('previous_event_digest', 64)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('event_digest', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('digest_key_id', 64);
            $table->string('encryption_version', 64);
            $table->string('encryption_key_id', 64);
            $table->unique(['entry_id', 'event_sequence'], 'dpn_entry_sequence_uq');
            $table->unique(['actor_id', 'command_name', 'command_key', 'event_ordinal'], 'dpn_command_ordinal_uq');
            $table->foreign(['evidence_id', 'entry_id'], 'dpn_evidence_entry_fk')->references(['id', 'entry_id'])->on('dealer_penalty_evidence')->restrictOnDelete();
            $table->index(['entry_id', 'occurred_at'], 'dpn_entry_time_idx');
        });
        DB::statement('ALTER TABLE dealer_penalty_events ADD CONSTRAINT dpn_versions_ck CHECK (event_sequence > 0 AND after_version = before_version + 1 AND after_version = event_sequence)');
        DB::statement('ALTER TABLE dealer_penalty_events ADD CONSTRAINT dpn_counts_ck CHECK (evidence_count_after > 0 AND evidence_count_after >= evidence_count_before)');
        DB::statement('ALTER TABLE dealer_penalty_events ADD CONSTRAINT dpn_chain_ck CHECK ((event_sequence = 1 AND previous_event_digest IS NULL AND evidence_count_before = 0) OR (event_sequence > 1 AND previous_event_digest IS NOT NULL AND evidence_count_before > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_penalty_events');
    }
};
