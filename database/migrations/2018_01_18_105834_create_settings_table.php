<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->string('section', 25)->default('');
            $table->string('subsection', 25)->default('');
            $table->string('name', 25)->default('');
            $table->string('value', 1000)->default('');
            $table->text('hint', 65535);
            $table->string('setting', 64)->default('')->unique('ui_settings_setting');
            $table->primary(['section', 'subsection', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('settings');
    }
};
