<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\Media\FfmpegFrameGrabber;
use App\Services\AdditionalProcessing\Media\MediaToolFactory;
use FFMpeg\Driver\FFMpegDriver;
use FFMpeg\FFMpeg;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FfmpegFrameGrabberTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_caps_decoder_and_filter_threads(): void
    {
        $arguments = $this->grabArguments(threads: 2);

        $input = array_search('-i', $arguments, true);
        $decoderThreads = array_search('-threads', $arguments, true);
        $this->assertIsInt($decoderThreads);
        $this->assertSame('2', $arguments[$decoderThreads + 1]);
        $this->assertLessThan($input, $decoderThreads, '-threads must come before -i to limit the decoder');

        $filterThreads = array_search('-filter_threads', $arguments, true);
        $this->assertIsInt($filterThreads);
        $this->assertSame('2', $arguments[$filterThreads + 1]);
    }

    #[Test]
    public function it_leaves_ffmpeg_thread_defaults_when_the_cap_is_zero(): void
    {
        $arguments = $this->grabArguments(threads: 0);

        $this->assertNotContains('-threads', $arguments);
        $this->assertNotContains('-filter_threads', $arguments);
    }

    /**
     * @return list<string>
     */
    private function grabArguments(int $threads): array
    {
        $arguments = [];
        $driver = Mockery::mock(FFMpegDriver::class);
        $driver->shouldReceive('command')->once()->andReturnUsing(function (array $command) use (&$arguments): string {
            $arguments = $command;

            return '';
        });
        $ffmpeg = Mockery::mock(FFMpeg::class);
        $ffmpeg->shouldReceive('getFFMpegDriver')->andReturn($driver);
        $tools = Mockery::mock(MediaToolFactory::class);
        $tools->shouldReceive('ffmpeg')->andReturn($ffmpeg);

        (new FfmpegFrameGrabber($tools, threads: $threads))->grab('/missing/video.mkv', null, 10.0, 5.0, 4, sys_get_temp_dir());

        return $arguments;
    }
}
