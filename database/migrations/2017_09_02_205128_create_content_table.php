<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('content', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->string('url', 2000)->nullable();
            $table->text('body')->nullable();
            $table->string('metadescription', 1000);
            $table->string('metakeywords', 1000);
            $table->integer('contenttype');
            $table->integer('showinmenu');
            $table->integer('status');
            $table->integer('ordinal');
            $table->integer('role')->default(0);
            $table->index(
                [
                    'showinmenu',
                    'status',
                    'ordinal',
                    'role',
                ],
                'ix_showinmenu_status_contenttype_role'
            );
            DB::update('ALTER TABLE content AUTO_INCREMENT = 1000001');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('content');
    }
}
