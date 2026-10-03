<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('period_type', ['weekly', 'monthly']);
            $table->dateTime('period_start');
            $table->dateTime('period_end'); // Exclusive boundary.
            $table->dateTime('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->unique(['period_type', 'period_start', 'period_end'], 'awards_period_unique');
            $table->index('published_at');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE awards ADD CONSTRAINT awards_period_check CHECK (period_end > period_start)');
        DB::statement('ALTER TABLE awards ADD CONSTRAINT awards_publication_check CHECK ((published_at IS NULL AND published_by IS NULL) OR (published_at IS NOT NULL AND published_by IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
