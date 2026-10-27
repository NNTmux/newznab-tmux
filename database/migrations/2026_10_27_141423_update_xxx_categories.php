<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames and describes the XXX categories and adds JAV. SD moves to 6030 (the old XviD row); the old SD row 6080 is
 * relabelled "SD (legacy)" so two XXX rows are never both titled "SD".
 *
 * Retired categories (WMV, HD Clips, SD Clips, old SD 6080, WEBDL) are left in place on purpose: their releases are
 * moved and the rows deleted by `php artisan releases:recategorize-xxx --apply`. Disabling them here would make
 * release processing delete their releases.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{title: string, description: ?string, old_title: string}>
     */
    private const array CATEGORIES = [
        6030 => ['title' => 'SD', 'description' => 'Video below 720p', 'old_title' => 'XviD'],
        6040 => ['title' => 'HD', 'description' => 'Video from 720p to below 2160p', 'old_title' => 'x264'],
        6045 => ['title' => 'UHD', 'description' => 'Video at 2160p or above', 'old_title' => 'UHD'],
        6046 => ['title' => 'VR', 'description' => 'VR content, regardless of resolution', 'old_title' => 'VR'],
        6047 => ['title' => 'OnlyFans', 'description' => 'Releases positively identified as OnlyFans', 'old_title' => 'OnlyFans'],
        6050 => ['title' => 'Pack', 'description' => 'Video collections', 'old_title' => 'Packs'],
        6060 => ['title' => 'ImgSet', 'description' => 'Image collections', 'old_title' => 'Imageset'],
        6010 => ['title' => 'DVD', 'description' => 'Full DVD images or structures', 'old_title' => 'DVD'],
        6999 => ['title' => 'Other', 'description' => 'Adult content without a confident match', 'old_title' => 'Other'],
        6080 => ['title' => 'SD (legacy)', 'description' => null, 'old_title' => 'SD'],
    ];

    /**
     * Existing rows only get a new title and description, so admin settings such as status are kept.
     * Missing target rows are created; older installs can lack VR (6046) and OnlyFans (6047) because the
     * categories seeder used to delete and reinsert every category without them.
     */
    public function up(): void
    {
        $categories = self::CATEGORIES + [
            6048 => ['title' => 'JAV', 'description' => 'Releases positively identified as JAV', 'old_title' => 'JAV'],
        ];

        Schema::disableForeignKeyConstraints();

        foreach ($categories as $id => $category) {
            if (DB::table('categories')->where('id', $id)->exists()) {
                DB::table('categories')
                    ->where('id', $id)
                    ->update(['title' => $category['title'], 'description' => $category['description']]);

                continue;
            }

            // The retired 6080 row is only relabelled, never created.
            if ($id === 6080) {
                continue;
            }

            DB::table('categories')->insert([
                'id' => $id,
                'title' => $category['title'],
                'root_categories_id' => 6000,
                'status' => 1,
                'description' => $category['description'],
                'disablepreview' => 0,
                'minsizetoformrelease' => 0,
                'maxsizetoformrelease' => 0,
            ]);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        foreach (self::CATEGORIES as $id => $category) {
            DB::table('categories')
                ->where('id', $id)
                ->update(['title' => $category['old_title'], 'description' => $id === 6047 ? 'OnlyFans releases' : null]);
        }

        if (! DB::table('releases')->where('categories_id', 6048)->exists()) {
            DB::table('categories')->where('id', 6048)->delete();
        }
    }
};
