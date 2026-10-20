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
        Schema::table('dealers', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
        });
        // Self-contained backfill: only the new column is changed, never updated_at or history.
        $reserved = ['admin', 'dealer', 'dealers', 'sales', 'login', 'logout', 'register', 'profile', 'account',
            'api', 'sanctum', 'storage', 'build', 'up', 'health', 'leaderboard', 'awards', 'submissions',
            'forgot-password', 'reset-password', 'password', 'favicon', 'robots', '_ignition'];
        DB::table('dealers')->orderBy('id')->chunkById(200, function ($dealers) use ($reserved) {
            foreach ($dealers as $dealer) {
                $base = substr(Str::slug($dealer->name), 0, 180) ?: 'dai-ly';
                $slug = $base;
                for ($suffix = 2; in_array($slug, $reserved, true) || DB::table('dealers')->where('slug', $slug)->exists(); $suffix++) {
                    $slug = $base.'-'.$suffix;
                }
                DB::table('dealers')->where('id', $dealer->id)->update(['slug' => $slug]);
            }
        });
        DB::statement('ALTER TABLE dealers MODIFY slug VARCHAR(255) NOT NULL');
        DB::unprepared("CREATE TRIGGER dealers_immutable_slug BEFORE UPDATE ON dealers FOR EACH ROW
            BEGIN
                IF NOT (BINARY NEW.slug <=> BINARY OLD.slug) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Dealer slug is immutable';
                END IF;
            END");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS dealers_immutable_slug');
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropUnique('dealers_slug_unique');
            $table->dropColumn('slug');
        });
    }
};
