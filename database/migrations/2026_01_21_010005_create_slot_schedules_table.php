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
        Schema::create('slot_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_room_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->enum('day', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu']);
            $table->unsignedTinyInteger('slot_number'); // 1-8
            $table->enum('status', ['normal', 'pending_swap'])->default('normal');
            $table->enum('generated_by', ['system', 'admin'])->default('system');
            $table->timestamps();

            // Prevent teacher conflicts - teacher can only teach one class per slot
            $table->unique(['teacher_id', 'day', 'slot_number'], 'unique_teacher_day_slot');
            // One slot per class per day
            $table->unique(['class_room_id', 'day', 'slot_number'], 'unique_class_day_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_schedules');
    }
};
