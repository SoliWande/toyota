<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_submissions', function (Blueprint $table) {
            $table->enum('source', ['sales', 'dealer_qr'])->default('sales');
            $table->string('phone_normalized', 16)->nullable()->index();
            $table->string('vehicle_description', 255)->nullable();
            $table->dateTime('consented_at')->nullable();
            $table->string('consent_version', 40)->nullable();
        });
        DB::statement('ALTER TABLE customer_submissions DROP CHECK submissions_profile_check');
        DB::statement('ALTER TABLE customer_submissions MODIFY sales_id BIGINT UNSIGNED NULL, MODIFY facebook_url VARCHAR(2048) NULL, MODIFY facebook_url_normalized VARCHAR(255) COLLATE utf8mb4_bin NULL');
        DB::statement('ALTER TABLE customer_submissions ADD CONSTRAINT submissions_profile_check CHECK ((facebook_url IS NULL AND facebook_url_normalized IS NULL) OR (facebook_url IS NOT NULL AND CHAR_LENGTH(facebook_url) > 0 AND facebook_url_normalized IS NOT NULL AND CHAR_LENGTH(facebook_url_normalized) > 0))');
        DB::statement("ALTER TABLE customer_submissions ADD CONSTRAINT submissions_source_check CHECK ((source = 'sales' AND sales_id IS NOT NULL AND facebook_url IS NOT NULL) OR (source = 'dealer_qr' AND sales_id IS NULL AND dealer_id IS NOT NULL AND phone_normalized IS NOT NULL AND vehicle_description IS NOT NULL AND address_line IS NOT NULL AND province_code IS NOT NULL AND ward_code IS NOT NULL AND consented_at IS NOT NULL AND consent_version IS NOT NULL))");
        DB::statement("ALTER TABLE customer_submissions ADD CONSTRAINT submissions_approved_identity_check CHECK (status <> 'approved' OR (facebook_url_normalized IS NOT NULL AND CHAR_LENGTH(facebook_url_normalized) > 0))");
    }

    public function down(): void
    {
        if (DB::table('customer_submissions')->where('source', 'dealer_qr')->exists()) {
            throw new RuntimeException('Cannot remove QR registration support while QR registrations exist.');
        }
        DB::statement('ALTER TABLE customer_submissions DROP CHECK submissions_source_check, DROP CHECK submissions_approved_identity_check, DROP CHECK submissions_profile_check');
        DB::statement('ALTER TABLE customer_submissions DROP FOREIGN KEY customer_submissions_sales_id_foreign');
        DB::statement('ALTER TABLE customer_submissions MODIFY sales_id BIGINT UNSIGNED NOT NULL, MODIFY facebook_url VARCHAR(2048) NOT NULL, MODIFY facebook_url_normalized VARCHAR(255) COLLATE utf8mb4_bin NOT NULL');
        DB::statement('ALTER TABLE customer_submissions ADD CONSTRAINT customer_submissions_sales_id_foreign FOREIGN KEY (sales_id) REFERENCES users (id)');
        DB::statement('ALTER TABLE customer_submissions ADD CONSTRAINT submissions_profile_check CHECK (CHAR_LENGTH(facebook_url_normalized) > 0)');
        Schema::table('customer_submissions', function (Blueprint $table) {
            $table->dropIndex(['phone_normalized']);
            $table->dropColumn(['source', 'phone_normalized', 'vehicle_description', 'consented_at', 'consent_version']);
        });
    }
};
