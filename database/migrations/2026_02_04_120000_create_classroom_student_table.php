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
        Schema::create('classroom_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            // 'kelas' table represents the Classroom (Year + Level + Section)
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();

            // Status in the class (e.g., active, transferred_out, promoted)
            $table->enum('status', ['active', 'transferred', 'promoted', 'retained', 'graduated'])->default('active');

            $table->timestamps();

            // Prevent duplicate entry for same student in same class instance
            $table->unique(['student_id', 'kelas_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classroom_student');
    }
};
