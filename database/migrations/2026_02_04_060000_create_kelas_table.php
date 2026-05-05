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
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tingkat_id')->constrained('tingkat')->cascadeOnDelete();
            $table->foreignId('rombel_id')->constrained('rombel')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name'); // "1A", "2B", etc. (computed: tingkat.level + rombel.name)
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();

            // Unique constraint: one kelas per tingkat-rombel-year combination
            $table->unique(['tingkat_id', 'rombel_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
