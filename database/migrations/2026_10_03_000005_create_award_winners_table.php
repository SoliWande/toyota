<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('award_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_id')->constrained();
            $table->enum('winner_type', ['sales', 'dealer']);
            $table->foreignId('sales_id')->nullable()->constrained('users');
            $table->foreignId('dealer_id')->nullable()->constrained();
            $table->unsignedTinyInteger('rank');
            $table->unsignedInteger('score');
            $table->string('winner_name_snapshot');
            $table->unsignedBigInteger('sales_dealer_id_snapshot')->nullable();
            $table->string('dealer_name_snapshot');
            $table->string('dealer_code_snapshot', 50);
            $table->unique(['award_id', 'winner_type', 'rank'], 'winners_award_type_rank_unique');
            $table->unique(['award_id', 'sales_id'], 'winners_award_sales_unique');
            $table->unique(['award_id', 'dealer_id'], 'winners_award_dealer_unique');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE award_winners ADD CONSTRAINT winners_subject_check CHECK ((winner_type = 'sales' AND sales_id IS NOT NULL AND dealer_id IS NULL AND sales_dealer_id_snapshot IS NOT NULL) OR (winner_type = 'dealer' AND dealer_id IS NOT NULL AND sales_id IS NULL AND sales_dealer_id_snapshot IS NULL))");
        DB::statement('ALTER TABLE award_winners ADD CONSTRAINT winners_rank_check CHECK (`rank` BETWEEN 1 AND 3)');
        DB::statement('ALTER TABLE award_winners ADD CONSTRAINT winners_snapshot_check CHECK (CHAR_LENGTH(TRIM(winner_name_snapshot)) > 0 AND CHAR_LENGTH(TRIM(dealer_name_snapshot)) > 0 AND CHAR_LENGTH(TRIM(dealer_code_snapshot)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('award_winners');
    }
};
