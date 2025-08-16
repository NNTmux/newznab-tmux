<?php

namespace Tests\Unit;

use App\Services\ArchiveProcessingService;
use dariusiii\rarinfo\ArchiveInfo;
use Mockery;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

class ArchiveProcessingServiceTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[WithoutErrorHandler]
    public function testAnalyzeOkNotEncrypted(): void
    {
        $ai = Mockery::mock(ArchiveInfo::class);
        $ai->shouldReceive('setData')->once()->andReturn(true);
        $ai->error = '';
        $ai->shouldReceive('getSummary')->once()->andReturn(['main_type' => 1, 'is_encrypted' => 0]);

        $svc = new ArchiveProcessingService($ai);
        $res = $svc->analyze('BINARY');

        $this->assertTrue($res['ok']);
        $this->assertFalse($res['is_encrypted']);
        $this->assertIsArray($res['summary']);
    }

    #[WithoutErrorHandler]
    public function testAnalyzeEncryptedViaSummaryFlag(): void
    {
        $ai = Mockery::mock(ArchiveInfo::class);
        $ai->shouldReceive('setData')->once()->andReturn(true);
        $ai->error = '';
        $ai->shouldReceive('getSummary')->once()->andReturn(['main_type' => 1, 'is_encrypted' => 1]);

        $svc = new ArchiveProcessingService($ai);
        $res = $svc->analyze('BINARY');

        $this->assertTrue($res['ok']);
        $this->assertTrue($res['is_encrypted']);
    }

    #[WithoutErrorHandler]
    public function testAnalyzeErrorWhenSetDataFails(): void
    {
        $ai = Mockery::mock(ArchiveInfo::class);
        $ai->shouldReceive('setData')->once()->andReturn(false);
        $ai->error = 'Bad data';

        $svc = new ArchiveProcessingService($ai);
        $res = $svc->analyze('BINARY');

        $this->assertFalse($res['ok']);
        $this->assertNotNull($res['error']);
    }

    #[WithoutErrorHandler]
    public function testGettersProxyToArchiveInfo(): void
    {
        $ai = Mockery::mock(ArchiveInfo::class);
        $ai->shouldReceive('getArchiveFileList')->once()->andReturn([['name' => 'file.txt']]);
        $ai->shouldReceive('getFileData')->once()->with('name', 'source')->andReturn('DATA');
        $ai->shouldReceive('extractFile')->once()->with('name', Mockery::type('string'))->andReturn(true);

        $svc = new ArchiveProcessingService($ai);

        $this->assertSame([['name' => 'file.txt']], $svc->getFileList());
        $this->assertSame('DATA', $svc->getFileData('name', 'source'));
        $this->assertTrue($svc->extractFile('name', '/tmp/path'));
    }
}
