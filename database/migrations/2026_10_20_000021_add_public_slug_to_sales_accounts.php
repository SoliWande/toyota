<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('public_slug', 34)->nullable()->unique();
        });
        DB::table('users')->where('role', 'sales')->select('id')->orderBy('id')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                // No names, contact details, account IDs or application keys in the public identifier.
                DB::table('users')->where('id', $user->id)->update(['public_slug' => 's-'.str_replace('-', '', (string) Str::uuid())]);
            }
        });
        DB::unprepared("CREATE TRIGGER users_immutable_public_slug BEFORE UPDATE ON users FOR EACH ROW BEGIN IF OLD.public_slug IS NOT NULL AND NOT (OLD.public_slug <=> NEW.public_slug) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sales public slug is immutable'; END IF; END");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS users_immutable_public_slug');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('public_slug'));
    }
};
