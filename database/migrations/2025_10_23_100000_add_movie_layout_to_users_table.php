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
        if (! Schema::hasColumn('users', 'movie_layout')) {
            Schema::table('users', function (Blueprint $table) {
                $table->tinyInteger('movie_layout')->default(2)->comment('1=1-column, 2=2-columns')->after('timezone');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('movie_layout');
        });
    }
};
