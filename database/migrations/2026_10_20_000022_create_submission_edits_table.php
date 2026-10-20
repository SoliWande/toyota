<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('submission_id_snapshot')->index();
            $table->foreignId('edited_by')->constrained('users')->restrictOnDelete();
            $table->json('changes');
            $table->dateTime('edited_at');
            $table->index(['customer_submission_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_edits');
    }
};
