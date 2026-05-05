<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Change ENUMs to Strings to support new values (daily, hadir, izin, sakit, alpha)
            // Using DB raw statement for compatibility
            DB::statement("ALTER TABLE attendances MODIFY COLUMN type VARCHAR(255) NULL");
            DB::statement("ALTER TABLE attendances MODIFY COLUMN status VARCHAR(255) NOT NULL DEFAULT 'hadir'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Revert back to ENUMs (approximation, data loss possible if new values present)
            DB::statement("ALTER TABLE attendances MODIFY COLUMN type ENUM('masuk', 'keluar')");
            DB::statement("ALTER TABLE attendances MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
        });
    }
};
