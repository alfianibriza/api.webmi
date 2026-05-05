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
        Schema::table('class_rooms', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('id')->constrained('academic_years')->nullOnDelete();
            $table->string('label')->nullable()->after('name'); // A, B, C, etc.
            $table->enum('status', ['active', 'inactive'])->default('active')->after('grade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_rooms', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn(['academic_year_id', 'label', 'status']);
        });
    }
};
