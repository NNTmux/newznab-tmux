<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string INDEX_NAME = 'ix_releases_tv_processing';

    public function up(): void
    {
        if (! Schema::hasTable('releases') || Schema::hasIndex('releases', self::INDEX_NAME)) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mariadb', 'mysql'], true)) {
            $table = str_replace('`', '``', DB::getTablePrefix().'releases');
            DB::statement(sprintf(
                'CREATE INDEX `%s` ON `%s` (`videos_id`, `tv_episodes_id`, `leftguid`, `postdate` DESC)',
                self::INDEX_NAME,
                $table,
            ));

            return;
        }

        Schema::table('releases', function (Blueprint $table): void {
            $table->index(
                ['videos_id', 'tv_episodes_id', 'leftguid', 'postdate'],
                self::INDEX_NAME,
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('releases') || ! Schema::hasIndex('releases', self::INDEX_NAME)) {
            return;
        }

        Schema::table('releases', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX_NAME);
        });
    }
};
