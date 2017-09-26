<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReleasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 255)->default('');
            $table->string('searchname', 255)->default('');
            $table->integer('totalpart');
            $table->integer('groups_id');
            $table->unsignedBigInteger('size')->default(0);
            $table->dateTime('postdate')->nullable();
            $table->dateTime('adddate')->nullable();
            $table->timestamp('updatetime');
            $table->string('gid', 32)->nullable();
            $table->string('guid', 40);
            $table->char('leftguid', 1);
            $table->string('fromname', 255);
            $table->float('completion')->default(0);
            $table->integer('categories_id')->default(10);
            $table->unsignedMediumInteger('videos_id')->default(0);
            $table->mediumInteger('tv_episodes_id')->default(0);
            $table->mediumInteger('imdbid');
            $table->integer('xxxinfo_id')->default(0);
            $table->integer('musicinfo_id')->nullable();
            $table->integer('consoleinfo_id')->nullable();
            $table->integer('gamesinfo_id')->default(0);
            $table->integer('bookinfo_id')->nullable();
            $table->integer('anidbid')->nullable();
            $table->integer('predb_id')->default(0);
            $table->integer('grabs')->default(0);
            $table->integer('comments')->default(0);
            $table->tinyInteger('passwordstatus')->default(0);
            $table->integer('rarinnerfilecount')->default(0);
            $table->tinyInteger('haspreview')->default(0);
            $table->tinyInteger('nfostatus')->default(0);
            $table->tinyInteger('jpgstatus')->default(0);
            $table->tinyInteger('videostatus')->default(0);
            $table->tinyInteger('audiostatus')->default(0);
            $table->tinyInteger('dehashstatus')->default(0);
            $table->tinyInteger('reqidstatus')->default(0);
            $table->binary('nzb_guid')->nullable();
            $table->tinyInteger('nzbstatus')->default(0);
            $table->tinyInteger('iscategorized')->default(0);
            $table->tinyInteger('isrenamed')->default(0);
            $table->tinyInteger('ishashed')->default(0);
            $table->tinyInteger('isrequestid')->default(0);
            $table->tinyInteger('proc_pp')->default(0);
            $table->tinyInteger('proc_sorter')->default(0);
            $table->tinyInteger('proc_par2')->default(0);
            $table->tinyInteger('proc_nfo')->default(0);
            $table->tinyInteger('proc_files')->default(0);
            $table->tinyInteger('proc_uid')->default(0);
            $table->tinyInteger('proc_srr')->default(0);
            $table->tinyInteger('proc_hash16k')->default(0);
            $table->primary(['id', 'categories_id']);
            $table->index(['name'], 'ix_releases_name');
            $table->index(['groups_id', 'passwordstatus'], 'ix_releases_groups_id');
            $table->index(['postdate', 'searchname'], 'ix_releases_postdate_searchname');
            $table->index('guid', 'ix_releases_guid');
            $table->index(['leftguid', 'predb_id'], 'ix_releases_leftguid ');
            $table->index('nzb_guid', 'ix_releases_nzb_guid');
            $table->index('videos_id', 'ix_releases_videos_id');
            $table->index('tv_episodes_id', 'ix_releases_tv_episodes_id');
            $table->index('imdbid', 'ix_releases_imdbid ');
            $table->index('xxxinfo_id', 'ix_releases_xxxinfo_id');
            $table->index(['musicinfo_id', 'passwordstatus'], 'ix_releases_musicinfo_id');
            $table->index('consoleinfo_id', 'ix_releases_consoleinfo_id');
            $table->index('gamesinfo_id', 'ix_releases_gamesinfo_id');
            $table->index('bookinfo_id', 'ix_releases_bookinfo_id');
            $table->index('anidbid', 'ix_releases_anidbid');
            $table->index(['predb_id', 'searchname'], 'ix_releases_predb_id_searchname');
            $table->index(['haspreview', 'passwordstatus'], 'ix_releases_haspreview_passwordstatus');
            $table->index('passwordstatus', 'ix_releases_passwordstatus');
            $table->index(['nfostatus', 'size'], 'ix_releases_nfostatus');
            $table->index(['dehashstatus', 'ishashed'], 'ix_releases_dehashstatus');
            $table->index(['adddate', 'reqidstatus', 'isrequestid'], 'ix_releases_reqidstatus');

            DB::statement('ALTER TABLE releases CHANGE imdbid imdbid MEDIUMINT(7) UNSIGNED ZEROFILL NULL');
            DB::statement(
                'ALTER TABLE releases PARTITION BY RANGE (categories_id) (
                            PARTITION misc    VALUES LESS THAN (1000),
                            PARTITION console VALUES LESS THAN (2000),
                            PARTITION movies  VALUES LESS THAN (3000),
                            PARTITION audio   VALUES LESS THAN (4000),
                            PARTITION pc      VALUES LESS THAN (5000),
                            PARTITION tv      VALUES LESS THAN (6000),
                            PARTITION xxx     VALUES LESS THAN (7000),
                            PARTITION books   VALUES LESS THAN (8000)
                            )'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('releases');
    }
}
