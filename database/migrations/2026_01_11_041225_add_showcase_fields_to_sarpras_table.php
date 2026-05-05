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
        Schema::table('sarpras', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->integer('order')->default(0)->after('image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sarpras', function (Blueprint $table) {
            $table->dropColumn(['description', 'order']);
        });
    }
};
