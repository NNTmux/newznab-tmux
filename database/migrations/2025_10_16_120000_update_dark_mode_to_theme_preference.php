<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, add the new theme_preference column
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme_preference', 10)->default('light')->after('dark_mode');
        });

        // Migrate existing dark_mode values to theme_preference
        DB::table('users')->where('dark_mode', true)->update(['theme_preference' => 'dark']);
        DB::table('users')->where('dark_mode', false)->update(['theme_preference' => 'light']);

        // Drop the old dark_mode column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dark_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the dark_mode column
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('dark_mode')->default(false)->after('notes');
        });

        // Migrate theme_preference back to dark_mode
        DB::table('users')->where('theme_preference', 'dark')->update(['dark_mode' => true]);
        DB::table('users')->whereIn('theme_preference', ['light', 'system'])->update(['dark_mode' => false]);

        // Drop theme_preference column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme_preference');
        });
    }
};

