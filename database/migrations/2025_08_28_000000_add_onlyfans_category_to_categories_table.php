<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('categories')->insert([
            'id' => 6047,
            'title' => 'OnlyFans',
            'root_categories_id' => 6000, // XXX_ROOT
            'status' => 1, // Active
            'description' => 'OnlyFans releases',
            'disablepreview' => 0,
            'minsizetoformrelease' => 0,
            'maxsizetoformrelease' => 0,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('categories')->where('id', 6047)->delete();
    }
};

