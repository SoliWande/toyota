<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_audit_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('batch_id')->constrained('call_audit_batches')->restrictOnDelete();
            $table->unsignedBigInteger('event_sequence');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('attempt_id')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('occurred_at', 6);
            $table->string('event_type', 64);
            $table->string('event_schema_version', 64);
            $table->string('command_name', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('command_key', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedInteger('event_ordinal');
            $table->unsignedInteger('before_version')->nullable();
            $table->unsignedInteger('after_version')->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->string('correlation_id', 64)->charset('ascii')->collation('ascii_bin');
            $table->json('metadata');
            $table->longText('private_payload_encrypted')->nullable();
            $table->char('previous_event_digest', 64)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->char('event_digest', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('digest_key_id', 64);

            $table->unique(['batch_id', 'event_sequence'], 'cae_batch_sequence_uq');
            $table->unique(['batch_id', 'actor_id', 'command_name', 'command_key', 'event_ordinal'], 'cae_command_event_uq');
            $table->foreign(['item_id', 'batch_id'], 'cae_item_batch_fk')->references(['id', 'batch_id'])->on('call_audit_items');
            $table->foreign(['attempt_id', 'item_id'], 'cae_attempt_item_fk')->references(['id', 'item_id'])->on('call_audit_attempts');
        });

        DB::statement('ALTER TABLE call_audit_events ADD CONSTRAINT cae_sequence_ck CHECK (event_sequence > 0)');
        DB::statement('ALTER TABLE call_audit_events ADD CONSTRAINT cae_attempt_item_ck CHECK (attempt_id IS NULL OR item_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('call_audit_events');
    }
};
