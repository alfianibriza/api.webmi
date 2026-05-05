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
        Schema::create('rombel_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('old_rombel_id')->constrained('class_rooms')->cascadeOnDelete();
            $table->foreignId('new_rombel_id')->constrained('class_rooms')->cascadeOnDelete();
            $table->timestamps();

            // Unique constraint: one mapping per old rombel per academic year
            $table->unique(['academic_year_id', 'old_rombel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rombel_mappings');
    }
};
