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
        // Teachers
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('nip')->nullable()->change();
            $table->enum('gender', ['L', 'P'])->nullable()->change();
            $table->string('birth_place')->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('position')->nullable()->change();
        });

        // Students
        Schema::table('students', function (Blueprint $table) {
            $table->enum('gender', ['L', 'P'])->nullable()->change();
            $table->string('grade')->nullable()->change(); // grade comment might be lost but ok
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // It's hard to reverse nullable to non-nullable if data exists with nulls.
        // For now, we can try to revert, but realistically we just leave them nullable or set defaults.
        Schema::table('teachers', function (Blueprint $table) {
            $table->enum('gender', ['L', 'P'])->nullable(false)->change();
            $table->string('birth_place')->nullable(false)->change();
            $table->date('birth_date')->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
            $table->string('position')->nullable(false)->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->enum('gender', ['L', 'P'])->nullable(false)->change();
            $table->string('grade')->nullable(false)->change();
        });
    }
};
