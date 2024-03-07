<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Triggers\Trigger;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('release_files', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->integer('releases_id')->unsigned()->comment('FK to releases.id');
            $table->string('name')->default('');
            $table->bigInteger('size')->unsigned()->default(0);
            $table->boolean('ishashed')->default(0)->index('ix_releasefiles_ishashed');
            $table->string('crc32')->default('');
            $table->timestamps();
            $table->boolean('passworded')->default(0);
            $table->primary(['releases_id', 'name']);
            $table->foreign('releases_id', 'FK_rf_releases')->references('id')->on('releases')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        Trigger::table('release_files')->key('check_rfinsert')->beforeInsert(function () {
            return 'IF NEW.name REGEXP "[a-fA-F0-9]{32}" THEN SET NEW.ishashed = 1; END IF;';
        });

        Trigger::table('release_files')->key('check_rfupdate')->beforeUpdate(function () {
            return 'IF NEW.name REGEXP "[a-fA-F0-9]{32}" THEN SET NEW.ishashed = 1; END IF;';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('release_files');
    }
};
