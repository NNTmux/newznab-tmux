<?php

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\Enums\DownloadKind;
use App\Services\AdditionalProcessing\UsenetDownloadService;
use App\Services\NNTP\NNTPService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UsenetDownloadServiceTest extends TestCase
{
    use CreatesProcessingConfiguration;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_downloads_binary_payloads_through_the_enum_driven_api(): void
    {
        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getMessagesByMessageID')
            ->once()
            ->with(['<abc>'], false)
            ->andReturn('BINARY-DATA');

        $service = new UsenetDownloadService($this->makeConfig(), $nntp);

        $result = $service->download(DownloadKind::Compressed, ['<abc>'], 'alt.binaries', 55, 'archive.rar');

        $this->assertTrue($result['success']);
        $this->assertSame('BINARY-DATA', $result['data']);
    }

    #[Test]
    public function it_marks_group_unavailable_errors_explicitly(): void
    {
        $error = new class
        {
            public function getMessage(): string
            {
                return 'No such news group';
            }
        };

        $nntp = Mockery::mock(NNTPService::class);
        $nntp->shouldReceive('getMessagesByMessageID')
            ->once()
            ->with(['<missing>'], false)
            ->andReturn($error);

        $service = new UsenetDownloadService($this->makeConfig(), $nntp);

        $result = $service->download(DownloadKind::Sample, ['<missing>']);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['groupUnavailable']);
        $this->assertStringContainsString('Group unavailable', (string) $result['error']);
    }

    #[Test]
    public function it_checks_minimum_download_sizes(): void
    {
        $service = new UsenetDownloadService($this->makeConfig(), Mockery::mock(NNTPService::class));

        $this->assertFalse($service->meetsMinimumSize(str_repeat('a', 40)));
        $this->assertTrue($service->meetsMinimumSize(str_repeat('a', 41)));
    }
}
