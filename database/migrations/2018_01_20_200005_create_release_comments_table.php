<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateReleaseCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('release_comments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id');
            $table->integer('releases_id')->unsigned()->index('ix_releasecomment_releases_id')->comment('FK to releases.id');
            $table->string('text', 2000)->default('');
            $table->boolean('isvisible')->default(1);
            $table->boolean('issynced')->default(0);
            $table->string('gid', 32)->nullable();
            $table->string('cid', 32)->nullable();
            $table->string('text_hash', 32)->default('');
            $table->string('username')->default('');
            $table->integer('users_id')->unsigned()->index('ix_releasecomment_userid');
            $table->timestamps();
            $table->string('host', 15)->nullable();
            $table->boolean('shared')->default(0);
            $table->string('shareid', 40)->default('');
            $table->string('siteid', 40)->default('');
            $table->bigInteger('sourceid')->unsigned()->nullable();
            $table->unique(['text_hash', 'releases_id'], 'ix_release_comments_hash_releases_id');
            $table->foreign('releases_id', 'FK_rc_releases')->references('id')->on('releases')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        DB::statement("ALTER TABLE release_comments ADD COLUMN nzb_guid BINARY(16) NOT NULL DEFAULT '0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::drop('release_comments');
    }
}
