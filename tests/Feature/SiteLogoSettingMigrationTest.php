<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SiteLogoSettingMigrationTest extends TestCase
{
    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge();

        $this->migration = require database_path('migrations/2026_08_29_082336_add_site_logo_setting_to_settings_table.php');
    }

    #[Test]
    public function it_is_skipped_when_the_schema_dump_has_already_replaced_the_settings_table(): void
    {
        $this->migration->up();
        $this->migration->down();

        $this->assertFalse(Schema::hasTable('settings'));
    }

    #[Test]
    public function it_adds_and_removes_the_site_logo_setting_on_legacy_installs(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        $this->migration->up();
        $this->assertSame('', DB::table('settings')->where('name', 'site_logo')->value('value'));

        $this->migration->down();
        $this->assertFalse(DB::table('settings')->where('name', 'site_logo')->exists());
    }
}
