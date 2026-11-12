<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('award_publications', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('award_id')->unique()->constrained()->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('monthly_scope_key', 7)->unique();
            $table->string('request_key', 64);
            $table->char('intent_digest', 64);
            $table->string('policy_version', 80);
            $table->string('digest_key_id', 80);
            $table->dateTime('source_cutoff_at', 6);
            $table->unsignedBigInteger('ledger_cutoff_id')->default(0);
            $table->unsignedInteger('batch_generation');
            $table->char('preview_fingerprint', 64);
            $table->longText('evidence_encrypted');
            $table->longText('receipt_encrypted');
            $table->char('evidence_digest', 64);
            $table->dateTime('published_at', 6);
            $table->unique(['actor_id', 'request_key'], 'publication_actor_request_unique');
        });
        DB::statement('ALTER TABLE award_publications ADD CONSTRAINT publication_generation_check CHECK (batch_generation BETWEEN 1 AND 64)');
        DB::statement("ALTER TABLE award_publications ADD CONSTRAINT publication_month_check CHECK (monthly_scope_key REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$')");
        DB::statement('ALTER TABLE award_publications ADD CONSTRAINT publication_cutoff_check CHECK (source_cutoff_at <= published_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('award_publications');
    }
};
