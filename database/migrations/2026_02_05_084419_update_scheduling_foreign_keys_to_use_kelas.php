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
        // 1. Class Subjects
        Schema::table('class_subjects', function (Blueprint $table) {
            try {
                $table->dropForeign(['class_room_id']);
            } catch (\Exception $e) {
            }
            $table->foreign('class_room_id')->references('id')->on('kelas')->onDelete('cascade');
        });

        // 2. Class Day Slots
        Schema::table('class_day_slots', function (Blueprint $table) {
            try {
                $table->dropForeign(['class_room_id']);
            } catch (\Exception $e) {
            }
            $table->foreign('class_room_id')->references('id')->on('kelas')->onDelete('cascade');
        });

        // 3. Teacher Class Constraints
        Schema::table('teacher_class_constraints', function (Blueprint $table) {
            try {
                $table->dropForeign(['class_room_id']);
            } catch (\Exception $e) {
            }
            $table->foreign('class_room_id')->references('id')->on('kelas')->onDelete('cascade');
        });

        // 4. Slot Schedules
        Schema::table('slot_schedules', function (Blueprint $table) {
            try {
                $table->dropForeign(['class_room_id']);
            } catch (\Exception $e) {
            }
            $table->foreign('class_room_id')->references('id')->on('kelas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_subjects', function (Blueprint $table) {
            $table->dropForeign(['class_room_id']);
            $table->foreign('class_room_id', 'class_subjects_class_room_id_foreign')->references('id')->on('class_rooms')->onDelete('cascade');
        });

        Schema::table('class_day_slots', function (Blueprint $table) {
            $table->dropForeign(['class_room_id']);
            $table->foreign('class_room_id', 'class_day_slots_class_room_id_foreign')->references('id')->on('class_rooms')->onDelete('cascade');
        });

        Schema::table('teacher_class_constraints', function (Blueprint $table) {
            $table->dropForeign(['class_room_id']);
            $table->foreign('class_room_id', 'teacher_class_constraints_class_room_id_foreign')->references('id')->on('class_rooms')->onDelete('cascade');
        });

        Schema::table('slot_schedules', function (Blueprint $table) {
            $table->dropForeign(['class_room_id']);
            $table->foreign('class_room_id', 'slot_schedules_class_room_id_foreign')->references('id')->on('class_rooms')->onDelete('cascade');
        });
    }
};
