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
            if (Schema::hasColumns('settings', ['lookuptvrage'])) {
                $table->renameColumn('lookuptvrage', 'lookuptv');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumns('settings', ['lookuptv'])) {
                $table->renameColumn('lookuptv', 'lookuptvrage');
            }
        });
    }
};
