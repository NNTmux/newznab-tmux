<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReleaseIdentitySuggestionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        Schema::create('predb', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('filename')->default('');
            $table->string('source')->default('fixture');
        });
        DB::table('predb')->insert(['id' => 1, 'title' => 'Kanata.Kara.S01E02.1080p.AMZN.WEB-DL.JPN.DDP2.0.H.264-ToonsHub']);
    }

    public function test_diagnostic_recovers_candidate_without_assigning_an_identity(): void
    {
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            $table->string('searchname');
            $table->integer('predb_id')->default(0);
        });
        DB::table('releases')->insert(['id' => 17, 'searchname' => 'kazata.kara.s1e2']);
        $exit = Artisan::call('releases:identity-suggestions', ['--release-id' => 17, '--candidate-id' => [1]]);
        $result = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(0, $exit);
        $this->assertSame(1, $result['candidate_predb_id']);
        $this->assertFalse($result['auto_link']);
        $this->assertSame('kazata.kara.s1e2', DB::table('releases')->value('searchname'));
        $this->assertSame(0, (int) DB::table('releases')->value('predb_id'));
        $this->assertSame(1, DB::table('predb')->count());
    }

    public function test_conflicting_episode_diagnostic_has_no_candidate(): void
    {
        $this->artisan('releases:identity-suggestions', ['query' => 'Kanata.Kara.S01E03.1080p.AMZN.WEB-DL.JPN.DDP2.0.H.264-ToonsHub', '--candidate-id' => [1]])
            ->expectsOutputToContain('"candidate_predb_id":null')->assertSuccessful();
    }
}
