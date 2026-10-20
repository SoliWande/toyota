<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'dealer', 'sales') NOT NULL DEFAULT 'sales'");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_dealer_account_check CHECK (role <> 'dealer' OR dealer_id IS NOT NULL)");
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('dealer_manager_key')->nullable()
                ->storedAs("CASE WHEN role = 'dealer' THEN dealer_id ELSE NULL END");
            $table->unique('dealer_manager_key', 'users_one_manager_per_dealer');
        });
    }

    public function down(): void
    {
        if (DB::table('users')->where('role', 'dealer')->exists()) {
            throw new RuntimeException('Dealer accounts exist; migrate them explicitly before reverting this schema.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_one_manager_per_dealer');
            $table->dropColumn('dealer_manager_key');
        });
        DB::statement('ALTER TABLE users DROP CHECK users_dealer_account_check');
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'sales') NOT NULL DEFAULT 'sales'");
    }
};
