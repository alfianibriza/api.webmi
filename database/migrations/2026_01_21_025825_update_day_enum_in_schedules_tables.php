<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // We need to use raw SQL to modify ENUM columns in MySQL/MariaDB
        // For SQLite it handles text so it's less strict, but for production safety:

        $tables = ['slot_schedules', 'class_day_slots', 'teacher_class_constraints'];

        foreach ($tables as $table) {
            // Using DB::statement to alter the enum definition
            // Added 'minggu' and kept 'jumat' (even if not used) and others

            // Check if driver is mysql
            if (config('database.default') === 'mysql') {
                DB::statement("ALTER TABLE {$table} MODIFY COLUMN day ENUM('senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu') NOT NULL");
            }
            // SQLite doesn't support modifying column types nicely, but usually it treats enums as check constraints or text
            // If using SQLite (local dev), we might just rely on application validation or recreate table if strictly needed.
            // Assuming MySQL/MariaDB for "ALTER TABLE ... MODIFY COLUMN"
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum
        $tables = ['slot_schedules', 'class_day_slots', 'teacher_class_constraints'];

        foreach ($tables as $table) {
            if (config('database.default') === 'mysql') {
                DB::statement("ALTER TABLE {$table} MODIFY COLUMN day ENUM('senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu') NOT NULL");
            }
        }
    }
};
