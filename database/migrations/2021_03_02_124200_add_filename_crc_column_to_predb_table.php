<?php

/** @noinspection PhpUnused, SpellCheckingInspection */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFilenameCrcColumnToPreDbTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasColumn('predb', 'filename_crc')) {
            Schema::table('predb', function (Blueprint $table) {
                $table->string('filename_crc', 8)->default('')->comment('Hex value of CRC32.');
                $table->index('filename_crc', 'ix_predb_filename_crc');
            });
        }
        Schema::getConnection()->update(<<<'Q1'
UPDATE predb SET filename_crc = hex(crc32(filename)) WHERE filename_crc = ?
Q1
            , ['']
        );
        Schema::getConnection()->update(<<<'T1'
CREATE OR REPLACE TRIGGER before_insert_on_predb_update_filename_crc
BEFORE INSERT ON predb
FOR EACH ROW
SET NEW.filename_crc = hex(crc32(NEW.filename))
T1
        );
        Schema::getConnection()->update(<<<'T2'
CREATE OR REPLACE TRIGGER before_update_on_predb_update_filename_crc
BEFORE UPDATE ON predb
FOR EACH ROW
SET NEW.filename_crc = hex(crc32(NEW.filename))
T2
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::getConnection()->update('DROP TRIGGER before_insert_on_predb_update_filename_crc');
        Schema::getConnection()->update('DROP TRIGGER before_update_on_predb_update_filename_crc');
        Schema::table('predb', function (Blueprint $table) {
            $table->dropColumn('filename_crc');
            $table->dropIndex('ix_predb_filename_crc');
        });
    }
}
