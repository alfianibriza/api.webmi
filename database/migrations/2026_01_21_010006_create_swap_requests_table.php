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
        Schema::create('swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->foreignId('target_teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->foreignId('schedule_id_1')->constrained('slot_schedules')->onDelete('cascade');
            $table->foreignId('schedule_id_2')->constrained('slot_schedules')->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('swap_requests');
    }
};
