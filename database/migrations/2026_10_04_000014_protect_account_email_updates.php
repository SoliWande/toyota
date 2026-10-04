<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Model events do not cover query-builder/bulk updates.
        DB::unprepared("CREATE TRIGGER users_immutable_email BEFORE UPDATE ON users FOR EACH ROW
            BEGIN
                IF NOT (BINARY NEW.email <=> BINARY OLD.email) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account email is immutable';
                END IF;
            END");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS users_immutable_email');
    }
};
