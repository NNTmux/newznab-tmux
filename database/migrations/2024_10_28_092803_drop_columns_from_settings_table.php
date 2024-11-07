<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumns('settings', ['section', 'subsection', 'hint', 'setting'])) {
                $table->dropPrimary();
                $table->dropIndex(['ui_settings_setting']);
                $table->dropColumn(['section', 'subsection', 'hint', 'setting']);
                $table->index(['name']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('section')->nullable();
            $table->string('subsection')->nullable();
            $table->string('hint')->nullable();
            $table->string('setting')->nullable();
        });
    }
};
