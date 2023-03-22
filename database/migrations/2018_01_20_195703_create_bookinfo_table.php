<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBookinfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bookinfo', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->string('title');
            $table->string('author');
            $table->string('asin', 128)->nullable()->unique('ix_bookinfo_asin');
            $table->string('isbn', 128)->nullable();
            $table->string('ean', 128)->nullable();
            $table->string('url', 1000)->nullable();
            $table->integer('salesrank')->unsigned()->nullable();
            $table->string('publisher')->nullable();
            $table->dateTime('publishdate')->nullable();
            $table->string('pages', 128)->nullable();
            $table->string('overview', 3000)->nullable();
            $table->string('genre');
            $table->boolean('cover')->default(0);
            $table->timestamps();
            $table->fullText(['author', 'title'], 'ix_bookinfo_author_title_ft');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('bookinfo');
    }
}
