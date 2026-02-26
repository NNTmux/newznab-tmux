<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

/**
 * Service for CLI output during additional processing.
 * Centralizes all echo/output functionality.
 */
class ConsoleOutputService
{
    public function __construct(
        private readonly bool $echoCLI = false
    ) {}

    public function echo(string $message, string $type = 'primary'): void
    {
        if ($this->echoCLI) {
            cli()->$type($message);
        }
    }

    public function debug(string $message): void
    {
        if ($this->echoCLI && config('app.env') === 'local' && config('app.debug') === true) {
            cli()->debug('DEBUG: '.$message);
        }
    }

    public function echoDescription(int $totalReleases): void
    {
        if ($totalReleases > 1 && $this->echoCLI) {
            $this->echo(
                PHP_EOL.
                'Additional post-processing, started at: '.
                now()->format('D M d, Y G:i a').
                PHP_EOL.
                'Downloaded: (xB)=yEnc article, (cB)=compressed part'.
                PHP_EOL.
                'Failures: fC#=Compressed(part #), fS=Sample, fM=Media(video), fA=Audio, fJ=JPEG, G=Missing group, T=Timeout skip'.
                PHP_EOL.
                'Processing: r=RAR, z=ZIP, (vRAW)=Inline video detected'.
                PHP_EOL.
                'Added: s=Sample image, j=JPEG image, A=Audio sample, a=Audio MediaInfo, v=Video sample'.
                PHP_EOL.
                'Added: m=Video MediaInfo, n=NFO, ^=Inner file details (RAR/ZIP)'.
                '',
                'header'
            );
        }
    }

    public function echoReleaseStart(int $releaseId, int $size): void
    {
        $this->echo(PHP_EOL.'['.$releaseId.']['.human_filesize($size, 1).']', 'primaryOver');
    }

    public function echoCompressedDownload(): void
    {
        $this->echo('(cB)', 'primaryOver');
    }

    public function echoCompressedFailure(int $failCount): void
    {
        $this->echo('fC'.$failCount, 'warningOver');
    }

    public function echoGroupUnavailable(): void
    {
        $this->echo('G', 'warningOver');
    }

    public function echoSampleDownload(): void
    {
        $this->echo('(sB)', 'primaryOver');
    }

    public function echoSampleFailure(): void
    {
        $this->echo('fS', 'warningOver');
    }

    public function echoMediaInfoDownload(): void
    {
        $this->echo('(mB)', 'primaryOver');
    }

    public function echoMediaInfoFailure(): void
    {
        $this->echo('fM', 'warningOver');
    }

    public function echoAudioDownload(): void
    {
        $this->echo('(aB)', 'primaryOver');
    }

    public function echoAudioFailure(): void
    {
        $this->echo('fA', 'warningOver');
    }

    public function echoJpgDownload(): void
    {
        $this->echo('(jB)', 'primaryOver');
    }

    public function echoJpgFailure(): void
    {
        $this->echo('fJ', 'warningOver');
    }

    public function echoArchiveMarker(string $marker): void
    {
        $this->echo($marker, 'primaryOver');
    }

    public function echoFileInfoAdded(): void
    {
        $this->echo('^', 'primaryOver');
    }

    public function echoSampleCreated(): void
    {
        $this->echo('s', 'primaryOver');
    }

    public function echoJpgSaved(): void
    {
        $this->echo('j', 'primaryOver');
    }

    public function echoAudioSampleCreated(): void
    {
        $this->echo('A', 'primaryOver');
    }

    public function echoAudioInfoAdded(): void
    {
        $this->echo('a', 'primaryOver');
    }

    public function echoVideoCreated(): void
    {
        $this->echo('v', 'primaryOver');
    }

    public function echoMediaInfoAdded(): void
    {
        $this->echo('m', 'primaryOver');
    }

    public function echoNfoFound(): void
    {
        $this->echo('n', 'primaryOver');
    }

    public function echoInlineVideo(): void
    {
        $this->echo('(vRAW)', 'primaryOver');
    }

    public function echoReleaseTimeout(int $releaseId, int $elapsedSeconds): void
    {
        $this->echo('T', 'warningOver');
        $this->echo(' Release '.$releaseId.' timed out after '.$elapsedSeconds.'s', 'warning');
    }

    public function echoReleaseTimeoutDeleted(int $releaseId, int $timeoutCount): void
    {
        $this->echo('T', 'warningOver');
        $this->echo(' Release '.$releaseId.' deleted after '.$timeoutCount.' timeout(s)', 'warning');
    }

    public function warning(string $message): void
    {
        $this->echo($message, 'warning');
    }

    public function echoReleaseDeleted(int $releaseId): void
    {
        $this->echo('Deleted broken release ID '.$releaseId, 'warningOver');
    }

    public function endOutput(): void
    {
        if ($this->echoCLI) {
            echo PHP_EOL;
        }
    }

    public function isEnabled(): bool
    {
        return $this->echoCLI;
    }
}
