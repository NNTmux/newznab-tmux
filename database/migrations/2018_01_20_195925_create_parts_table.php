<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePartsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->bigInteger('binaries_id')->unsigned()->default(0);
            $table->string('messageid')->default('');
            $table->bigInteger('number')->unsigned()->default(0);
            $table->integer('partnumber')->unsigned()->default(0);
            $table->integer('size')->unsigned()->default(0);
            $table->primary(['binaries_id', 'number']);
            $table->foreign('binaries_id', 'FK_binaries')->references('id')->on('binaries')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('parts');
    }
}
