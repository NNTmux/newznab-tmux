<?php

declare(strict_types=1);

use App\Enums\ConfigurationDomain;
use App\Services\Configuration\ConfigurationProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string INNERFILE_NEW = '/setup\.exe|password\.url|\.scr$|\.pif$|\.lnk$/i';

    private const string INNERFILE_OLD = '/setup.exe|password.url/i';

    public function up(): void
    {
        $this->ensureBinaryBlacklistIndScr();
        $this->upgradeInnerFileBlacklist();
    }

    public function down(): void
    {
        if (Schema::hasTable('binaryblacklist')) {
            DB::table('binaryblacklist')
                ->where('groupname', 'alt\\.binaries\\..*')
                ->where('regex', '-IND\\.Scr\\b')
                ->where('msgcol', 1)
                ->where('optype', 1)
                ->delete();
        }

        if (Schema::hasColumn('post_processing_configurations', 'inner_file_blacklist')) {
            DB::table('post_processing_configurations')
                ->where('inner_file_blacklist', self::INNERFILE_NEW)
                ->update(['inner_file_blacklist' => self::INNERFILE_OLD]);
            app(ConfigurationProvider::class)->forget(ConfigurationDomain::PostProcessing);
        }
    }

    /**
     * Block the -IND poster group that ships .scr payloads.
     */
    private function ensureBinaryBlacklistIndScr(): void
    {
        if (! Schema::hasTable('binaryblacklist')) {
            return;
        }

        $exists = DB::table('binaryblacklist')
            ->where('groupname', 'alt\\.binaries\\..*')
            ->where('regex', '-IND\\.Scr\\b')
            ->where('msgcol', 1)
            ->where('optype', 1)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('binaryblacklist')->insert([
            'groupname' => 'alt\\.binaries\\..*',
            'regex' => '-IND\\.Scr\\b',
            'msgcol' => 1,
            'optype' => 1,
            'status' => 1,
            'description' => 'Blocks malicious .scr-payload releases from -IND poster group',
            'last_activity' => null,
        ]);
    }

    /**
     * Add .scr/.pif/.lnk to the inner file blacklist when it still holds the old default.
     */
    private function upgradeInnerFileBlacklist(): void
    {
        if (! Schema::hasColumn('post_processing_configurations', 'inner_file_blacklist')) {
            return;
        }

        $updated = DB::table('post_processing_configurations')
            ->where(function ($query): void {
                $query->where('inner_file_blacklist', self::INNERFILE_OLD)
                    ->orWhere('inner_file_blacklist', '');
            })
            ->update(['inner_file_blacklist' => self::INNERFILE_NEW]);

        if ($updated > 0) {
            app(ConfigurationProvider::class)->forget(ConfigurationDomain::PostProcessing);
        }
    }
};
