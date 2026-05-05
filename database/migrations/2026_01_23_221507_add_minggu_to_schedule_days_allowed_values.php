<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // We need to modify the enum column to include 'minggu'.
        // Since Doctrine doesn't support ENUM modification directly in some drivers, 
        // using raw statement is often safer for ENUM changes in MySQL/MariaDB.

        // 1. slot_schedules table
        DB::statement("ALTER TABLE slot_schedules MODIFY COLUMN day ENUM('senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu') NOT NULL");

        // 2. class_day_slots table
        DB::statement("ALTER TABLE class_day_slots MODIFY COLUMN day ENUM('senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu') NOT NULL");

        // 3. teacher_class_constraints table
        DB::statement("ALTER TABLE teacher_class_constraints MODIFY COLUMN day ENUM('senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original (removing 'minggu') - only if specific entries are handled, 
        // but for safety we usually just leave it or strictly revert:

        // CAUTION: This might fail if there are 'minggu' records.
        // DB::statement("ALTER TABLE slot_schedules MODIFY COLUMN day ENUM('senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu') NOT NULL");
        // DB::statement("ALTER TABLE class_day_slots MODIFY COLUMN day ENUM('senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu') NOT NULL");
        // DB::statement("ALTER TABLE teacher_class_constraints MODIFY COLUMN day ENUM('senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu') NOT NULL");
    }
};
