<?php

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
            $table->string('name', 400)->default('\'\'')->index('ix_releases_name');
            $table->string('searchname', 300)->default('\'\'')->comment('Release search name');
            $table->integer('totalpart')->nullable()->default(0);
            $table->integer('groups_id')->unsigned()->default(0)->comment('FK to groups.id');
            $table->bigInteger('size')->unsigned()->default(0);
            $table->dateTime('postdate')->nullable()->default(null);
            $table->dateTime('adddate')->nullable()->default(null);
            $table->dateTime('updatetime')->default('current_timestamp()');
            $table->string('gid', 32)->nullable()->default(null);
            $table->string('guid', 40)->index('ix_releases_guid');
            $table->char('leftguid', 1)->comment('The first letter of the release guid');
            $table->string('fromname')->nullable()->default(null);
            $table->float('completion', 10, 0)->default(0);
            $table->integer('categories_id')->default(10);
            $table->integer('videos_id')->unsigned()->default(0)->index('ix_releases_videos_id')->comment('FK to videos.id of the parent series.');
            $table->integer('tv_episodes_id')->default(0)->index('ix_releases_tv_episodes_id')->comment('FK to tv_episodes.id for the episode.');
            $table->integer('imdbid')->unsigned()->nullable()->default(null)->index('ix_releases_imdbid');
            $table->integer('xxxinfo_id')->default(0)->index('ix_releases_xxxinfo_id');
            $table->integer('musicinfo_id')->nullable()->default(null)->comment('FK to musicinfo.id');
            $table->integer('consoleinfo_id')->nullable()->default(null)->index('ix_releases_consoleinfo_id')->comment('FK to consoleinfo.id');
            $table->integer('gamesinfo_id')->default(0)->index('ix_releases_gamesinfo_id');
            $table->integer('bookinfo_id')->nullable()->default(null)->index('ix_releases_bookinfo_id')->comment('FK to bookinfo.id');
            $table->integer('anidbid')->nullable()->default(null)->index('ix_releases_anidbid')->comment('FK to anidb_titles.anidbid');
            $table->integer('predb_id')->unsigned()->default(0)->comment('FK to predb.id');
            $table->integer('grabs')->unsigned()->default(0);
            $table->integer('comments')->default(0);
            $table->boolean('passwordstatus')->default(0)->index('ix_releases_passwordstatus');
            $table->integer('rarinnerfilecount')->default(0);
            $table->boolean('haspreview')->default(0);
            $table->boolean('nfostatus')->default(0);
            $table->boolean('jpgstatus')->default(0);
            $table->boolean('videostatus')->default(0);
            $table->boolean('audiostatus')->default(0);
            $table->boolean('dehashstatus')->default(0);
            $table->boolean('reqidstatus')->default(0);
            $table->binary('nzb_guid', 16)->nullable()->default(null)->index('ix_releases_nzb_guid');
            $table->boolean('nzbstatus')->default(0);
            $table->boolean('iscategorized')->default(0);
            $table->boolean('isrenamed')->default(0);
            $table->boolean('ishashed')->default(0);
            $table->boolean('proc_pp')->default(0);
            $table->boolean('proc_sorter')->default(0);
            $table->boolean('proc_par2')->default(0);
            $table->boolean('proc_nfo')->default(0);
            $table->boolean('proc_files')->default(0);
            $table->boolean('proc_uid')->default(0);
            $table->boolean('proc_srr')->default(0)->comment('Has the release been srr
processed');
            $table->boolean('proc_hash16k')->default(0)->comment('Has the release been
hash16k processed');
            $table->primary(['id', 'categories_id']);
            $table->index(['groups_id', 'passwordstatus'], 'ix_releases_groupsid');
            $table->index(['postdate', 'searchname'], 'ix_releases_postdate_searchname');
            $table->index(['leftguid', 'predb_id'], 'ix_releases_leftguid');
            $table->index(['musicinfo_id', 'passwordstatus'], 'ix_releases_musicinfo_id');
            $table->index(['predb_id', 'searchname'], 'ix_releases_predb_id_searchname');
            $table->index(['haspreview', 'passwordstatus'], 'ix_releases_haspreview_passwordstatus');
            $table->index(['nfostatus', 'size'], 'ix_releases_nfostatus');
            $table->index(['dehashstatus', 'ishashed'], 'ix_releases_dehashstatus');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('releases');
    }
}
