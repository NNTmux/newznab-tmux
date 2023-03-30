<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTvInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tv_info', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->integer('videos_id')->unsigned()->default(0)->primary()->comment('FK to video.id');
            $table->text('summary', 65535)->comment('Description/summary of the show.');
            $table->string('publisher', 50)->comment('The channel/network of production/release (ABC, BBC, Showtime, etc.).');
            $table->string('localzone', 50)->default('')->comment('The linux tz style identifier');
            $table->boolean('image')->default(0)->index('ix_tv_info_image')->comment('Does the video have a cover image?');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::drop('tv_info');
    }
}
