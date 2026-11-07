<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Nzb\NzbService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StreamingZipTest extends TestCase
{
    private const string GUID = '4aabfe07-daff-4d28-9d1d-d2a4ab7b6511';

    private string $nzbFile;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('guid');
            $table->string('searchname');
        });
        DB::table('releases')->insert(['guid' => self::GUID, 'searchname' => 'Stored.Release']);

        $this->nzbFile = sys_get_temp_dir().'/'.uniqid('nzb', true).'.nzb.gz';
        file_put_contents($this->nzbFile, gzencode('<nzb/>'));
    }

    protected function tearDown(): void
    {
        @unlink($this->nzbFile);

        parent::tearDown();
    }

    #[Test]
    public function it_zips_stored_releases_and_never_resolves_paths_for_unknown_guids(): void
    {
        config(['zipstream.case_insensitive_conflicts' => false]);
        $this->mock(NzbService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('nzbPath')->once()->with(self::GUID)->andReturn($this->nzbFile);
        });

        $zip = getStreamingZip([self::GUID, '/../../../../tmp/secret']);

        $this->assertTrue($zip->has('Stored.Release.nzb'));
        $this->assertFalse($zip->has('/../../../../tmp/secret.nzb'));
    }
}
