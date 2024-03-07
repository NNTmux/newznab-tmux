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
        Schema::create('predb', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8';
            $table->collation = 'utf8_unicode_ci';
            $table->increments('id')->comment('Primary key');
            $table->string('title')->default('')->unique('ix_predb_title');
            $table->string('nfo')->nullable()->index('ix_predb_nfo');
            $table->string('size', 50)->nullable();
            $table->string('category')->nullable();
            $table->dateTime('predate')->nullable()->index('ix_predb_predate');
            $table->string('source', 50)->default('')->index('ix_predb_source');
            $table->integer('requestid')->unsigned()->default(0);
            $table->integer('groups_id')->unsigned()->default(0)->comment('FK to groups');
            $table->boolean('nuked')->default(0)->comment('Is this pre nuked? 0 no 2 yes 1 un nuked 3 mod nuked');
            $table->string('nukereason')->nullable()->comment('If this pre is nuked, what is the reason?');
            $table->string('files', 50)->nullable()->comment('How many files does this pre have ?');
            $table->string('filename')->default('');
            $table->boolean('searched')->default(0)->index('ix_predb_searched');
            $table->index(['requestid', 'groups_id'], 'ix_predb_requestid');
            $table->fullText('filename', 'ft_predb_filename');
        });
        Trigger::table('predb')->key('insert_hashes')->afterInsert(function () {
            return 'INSERT INTO predb_hashes (hash, predb_id) VALUES (UNHEX(MD5(NEW.title)), NEW.id), (UNHEX(MD5(MD5(NEW.title))), NEW.id), (UNHEX(SHA1(NEW.title)), NEW.id), (UNHEX(SHA2(NEW.title, 256)), NEW.id), (UNHEX(MD5(CONCAT(NEW.title, NEW.requestid))), NEW.id), (UNHEX(MD5(CONCAT(NEW.title, NEW.requestid, NEW.requestid))), NEW.id);';
        });
        Trigger::table('predb')->key('update_hashes')->afterUpdate(function () {
            return 'IF NEW.title != OLD.title THEN DELETE FROM predb_hashes WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)), UNHEX(sha2(OLD.title, 256)), UNHEX(MD5(CONCAT(OLD.title, OLD.requestid)))) AND predb_id = OLD.id; INSERT INTO predb_hashes (hash, predb_id) VALUES (UNHEX(MD5(NEW.title)), NEW.id), (UNHEX(MD5(MD5(NEW.title))), NEW.id), (UNHEX(SHA1(NEW.title)), NEW.id), (UNHEX(SHA2(NEW.title, 256)), NEW.id), (UNHEX(MD5(CONCAT((NEW.title, NEW.requestid)))), NEW.id), (UNHEX(MD5(CONCAT(NEW.title, NEW.requestid, NEW.requestid))), NEW.id);END IF;';
        });
        Trigger::table('predb')->key('delete_hashes')->afterDelete(function () {
            return 'DELETE FROM predb_hashes WHERE hash IN ( UNHEX(md5(OLD.title)), UNHEX(md5(md5(OLD.title))), UNHEX(sha1(OLD.title)), UNHEX(sha2(OLD.title, 256)), UNHEX(MD5(CONCAT(OLD.title, OLD.requestid))), UNHEX(MD5(CONCAT(OLD.title, OLD.requestid, OLD.requestid)))) AND predb_id = OLD.id;';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('predb');
    }
};
