<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InnerFileBlacklistMigrationTest extends TestCase
{
    private const string OLD_DEFAULT = '/setup.exe|password.url/i';

    private const string NEW_DEFAULT = '/setup\.exe|password\.url|\.scr$|\.pif$|\.lnk$/i';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge();

        Schema::create('binaryblacklist', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('groupname')->nullable();
            $table->string('regex');
            $table->unsignedInteger('msgcol')->default(1);
            $table->unsignedInteger('optype')->default(1);
            $table->unsignedInteger('status')->default(1);
            $table->string('description')->nullable();
            $table->date('last_activity')->nullable();
        });
        Schema::create('post_processing_configurations', static function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->text('inner_file_blacklist');
        });
    }

    #[Test]
    public function it_upgrades_the_old_default_and_adds_the_ind_scr_rule_once(): void
    {
        DB::table('post_processing_configurations')->insert(['id' => 1, 'inner_file_blacklist' => self::OLD_DEFAULT]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(self::NEW_DEFAULT, DB::table('post_processing_configurations')->value('inner_file_blacklist'));
        $this->assertSame(1, DB::table('binaryblacklist')->where('regex', '-IND\\.Scr\\b')->count());
    }

    #[Test]
    public function it_keeps_a_customised_inner_file_blacklist(): void
    {
        DB::table('post_processing_configurations')->insert(['id' => 1, 'inner_file_blacklist' => '/custom\.exe/i']);

        $this->migration()->up();

        $this->assertSame('/custom\.exe/i', DB::table('post_processing_configurations')->value('inner_file_blacklist'));
    }

    #[Test]
    public function rolling_back_restores_the_old_default_and_removes_the_rule(): void
    {
        DB::table('post_processing_configurations')->insert(['id' => 1, 'inner_file_blacklist' => self::OLD_DEFAULT]);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(self::OLD_DEFAULT, DB::table('post_processing_configurations')->value('inner_file_blacklist'));
        $this->assertSame(0, DB::table('binaryblacklist')->count());
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_11_03_195736_add_ind_scr_blacklist_and_innerfile_extensions.php');
    }
}
