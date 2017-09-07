<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserDownloadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_downloads', function (Blueprint $table) {
            $table->engine = 'innodb';
            $table->increments('id');
            $table->unsignedInteger('users_id');
            $table->string('hosthash', 50);
            $table->dateTime('timestamp');
            $table->integer('releases_id')->comment('FK to releases.id');
            $table->index('users_id', 'ix_users_id');
            $table->index('timestamp', 'ix_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_downloads');
    }
}
