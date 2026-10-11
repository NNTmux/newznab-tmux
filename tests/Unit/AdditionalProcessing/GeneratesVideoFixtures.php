<?php

namespace Tests\Unit\AdditionalProcessing;

trait GeneratesVideoFixtures
{
    /**
     * Encode an ffmpeg test pattern; $source is a lavfi graph such as "testsrc" or "color=black".
     *
     * @param  list<string>  $outputOptions
     */
    private function generateVideo(string $path, string $source, int $seconds, array $outputOptions = ['-c:v', 'mjpeg']): string
    {
        $this->requireFfmpeg();

        $command = ['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', $source.(str_contains($source, '=') ? ':' : '=').'size=320x240:rate=10:duration='.$seconds, ...$outputOptions, $path];
        exec(implode(' ', array_map(escapeshellarg(...), $command)), $output, $status);
        $this->assertSame(0, $status, 'ffmpeg could not create '.$path);

        return $path;
    }

    private function requireFfmpeg(): void
    {
        if (! is_executable('/usr/bin/ffmpeg') || ! is_executable('/usr/bin/ffprobe')) {
            $this->markTestSkipped('ffmpeg and ffprobe are not installed.');
        }
    }
}
