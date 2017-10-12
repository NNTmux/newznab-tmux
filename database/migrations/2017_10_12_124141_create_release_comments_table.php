<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReleaseCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('release_comments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('releases_id')->unsigned()->index('ix_releasecomment_releases_id')->comment('FK to releases.id');
            $table->string('text', 2000)->default('\'\'');
            $table->boolean('isvisible')->default(1);
            $table->boolean('issynced')->default(0);
            $table->string('gid', 32)->nullable()->default(null);
            $table->string('cid', 32)->nullable()->default(null);
            $table->string('text_hash', 32)->default('\'\'');
            $table->string('username')->default('\'\'');
            $table->integer('users_id')->unsigned()->index('ix_releasecomment_userid');
            $table->timestamps();
            $table->string('host', 15)->nullable()->default(null);
            $table->boolean('shared')->default(0);
            $table->string('shareid', 40)->default('\'\'');
            $table->string('siteid', 40)->default('\'\'');
            $table->bigInteger('sourceid')->unsigned()->nullable()->default(null);
            $table->binary('nzb_guid', 16)->default('\'0$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$$UP$\'');
            $table->unique(['text_hash', 'releases_id'], 'ix_release_comments_hash_releases_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('release_comments');
    }
}
