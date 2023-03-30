<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangePasswordststatusReleasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->smallInteger('passwordstatus')->default(-1)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->boolean('passwordstatus')->default(0)->change();
        });
    }
}
