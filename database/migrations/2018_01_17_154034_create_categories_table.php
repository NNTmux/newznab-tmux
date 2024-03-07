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
        Schema::create('categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->string('title');
            $table->integer('parentid')->nullable()->index('ix_categories_parentid');
            $table->integer('status')->default(1)->index('ix_categories_status');
            $table->string('description')->nullable();
            $table->boolean('disablepreview')->default(0);
            $table->bigInteger('minsizetoformrelease')->unsigned()->default(0);
            $table->bigInteger('maxsizetoformrelease')->unsigned()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('categories');
    }
};
