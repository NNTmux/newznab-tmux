<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('categories')->insert(
            [
                'id' => 6046, // 6046 so it gets listed after XXX -> UHD
                'title' => 'VR',
                'root_categories_id' => 6000,
                'status' => 1,
                'description' => null,
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ]
        );
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('categories')->where('id', 6046)->delete();
    }
};
