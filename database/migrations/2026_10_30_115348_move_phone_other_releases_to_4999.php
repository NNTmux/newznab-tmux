<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Category::PC_PHONE_OTHER used to be 4040, a category that has never existed in the
 * categories table (Phone-Other is seeded as 4999). Releases the categorizer put in
 * 4040 are moved to 4999 so they show up in browse, search and the API again.
 */
return new class extends Migration
{
    private const int LEGACY_PHONE_OTHER = 4040;

    private const int PHONE_OTHER = 4999;

    public function up(): void
    {
        if (! Schema::hasTable('releases') || ! Schema::hasTable('categories')) {
            return;
        }

        if (DB::table('categories')->where('id', self::LEGACY_PHONE_OTHER)->exists()
            || ! DB::table('categories')->where('id', self::PHONE_OTHER)->exists()) {
            return;
        }

        DB::table('releases')
            ->where('categories_id', self::LEGACY_PHONE_OTHER)
            ->update(['categories_id' => self::PHONE_OTHER]);
    }

    /**
     * Not reversible: 4040 is not a valid category.
     */
    public function down(): void {}
};
