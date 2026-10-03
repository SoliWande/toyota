<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'sales'])->default('sales');
            $table->enum('status', ['pending', 'active', 'rejected', 'blocked'])->default('pending');
            $table->foreignId('dealer_id')->nullable()->constrained();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->dateTime('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->index(['role', 'status']);
            $table->index(['dealer_id', 'role', 'status']);
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_sales_dealer_check CHECK (role <> 'sales' OR dealer_id IS NOT NULL)");
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_review_pair_check CHECK ((reviewed_by IS NULL AND reviewed_at IS NULL) OR (reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CHECK users_review_pair_check');
        DB::statement('ALTER TABLE users DROP CHECK users_sales_dealer_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['dealer_id']);
            $table->dropIndex(['role', 'status']);
            $table->dropIndex(['dealer_id', 'role', 'status']);
            $table->dropColumn(['role', 'status', 'dealer_id', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'admin_note']);
        });
    }
};
