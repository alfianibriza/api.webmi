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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['simple', 'upload', 'text']);
            $table->dateTime('deadline')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // The Guru
            $table->enum('status', ['pending', 'submitted', 'approved', 'rejected'])->default('pending');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('completed_at')->nullable(); // For approved
            $table->text('submission_content')->nullable(); // For text type
            $table->string('submission_file')->nullable(); // For upload type
            $table->text('admin_feedback')->nullable(); // For rejection
            $table->timestamps();

            // Ensure a user is assigned to a task only once
            $table->unique(['task_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
    }
};
