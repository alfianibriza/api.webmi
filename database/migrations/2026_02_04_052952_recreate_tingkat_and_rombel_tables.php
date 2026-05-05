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
        // Create tingkat table (represents Kelas/Level)
        Schema::create('tingkat', function (Blueprint $table) {
            $table->id();
            $table->integer('level')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // Create rombel table (represents Detail Kelas, child of tingkat)
        Schema::create('rombel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('tingkat')->cascadeOnDelete();
            $table->string('name'); // A, B, C
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // Update kelas table column names to match new concept
        // kelas table now represents KelasAktif (snapshot)
        // No changes needed here, will be handled in models
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rombel');
        Schema::dropIfExists('tingkat');
    }
};
