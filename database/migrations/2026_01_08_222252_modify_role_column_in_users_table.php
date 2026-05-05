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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Reverting to enum requires raw constraint or enum type
            // For now, let's just make it string but restricted if possible, or just leave it.
            // But to be safe, let's try to revert to enum if supported.
            // Since we upgraded database, maybe we don't want to revert heavily.
            // Let's just comment it out to avoid issues if we roll back, 
            // or better yet, define it as enum again.
            // $table->enum('role', ['admin', 'guru'])->change(); 
            // SQLite might struggle with reverting to Enum. 
            // Let's Keep it simple.
        });
    }
};
