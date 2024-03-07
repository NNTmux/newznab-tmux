<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::create('firewall', function (Blueprint $table) {
            $table->increments('id');

            $table->string('ip_address', 39)->unique()->index();

            $table->boolean('whitelisted')->default(false); /// default is blacklist

            $table->timestamps();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('firewall');
    }
};
