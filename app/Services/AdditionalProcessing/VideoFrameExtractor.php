<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use Closure;
use Symfony\Component\Process\Process;
use Throwable;

class VideoFrameExtractor
{
    /**
     * @var Closure(list<string>, int): string
     */
    private readonly Closure $commandRunner;

    /**
     * @param  (callable(list<string>, int): string)|null  $commandRunner
     */
    public function __construct(
        private readonly ProcessingConfiguration $config,
        ?callable $commandRunner = null,
    ) {
        $this->commandRunner = $commandRunner === null
            ? Closure::fromCallable([$this, 'runProcess'])
            : Closure::fromCallable($commandRunner);
    }

    public function probeDecodableDuration(string $videoPath): ?float
    {
        $result = ($this->commandRunner)([
            $this->ffmpegBinary(),
            '-hide_banner',
            '-nostdin',
            '-v',
            'info',
            '-i',
            $videoPath,
            '-map',
            '0:v:0',
            '-an',
            '-f',
            'null',
            '-',
        ], $this->timeoutSeconds());

        if (preg_match_all('/time=\s*(\d{1,2}):(\d{2}):(\d{2}(?:\.\d+)?)/i', $result, $matches, PREG_SET_ORDER) === 0) {
            return null;
        }

        $lastMatch = $matches[array_key_last($matches)];

        return ((float) $lastMatch[1] * 3600)
            + ((float) $lastMatch[2] * 60)
            + (float) $lastMatch[3];
    }

    public function representativeTimestamp(?float $decodableDuration): float
    {
        if ($decodableDuration === null || $decodableDuration <= 0) {
            return 0.0;
        }

        return floor($decodableDuration * 0.85 * 1000) / 1000;
    }

    public function extractRepresentativeFrame(string $videoPath, string $framePath): bool
    {
        try {
            $duration = $this->probeDecodableDuration($videoPath);
        } catch (Throwable) {
            $duration = null;
        }

        foreach ($this->frameCommands($videoPath, $framePath, $duration) as $command) {
            if (is_file($framePath)) {
                @unlink($framePath);
            }
            try {
                ($this->commandRunner)($command, $this->timeoutSeconds());
            } catch (Throwable) {
                continue;
            }

            if ($this->isValidJpeg($framePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<list<string>>
     */
    private function frameCommands(string $videoPath, string $framePath, ?float $duration): array
    {
        $base = [
            $this->ffmpegBinary(),
            '-y',
            '-hide_banner',
            '-nostdin',
            '-loglevel',
            'error',
            '-i',
            $videoPath,
        ];

        $commands = [
            [...$base, '-vf', 'select=gt(scene\,0.30)', '-frames:v', '1', '-q:v', '2', $framePath],
            [...$base, '-vf', 'thumbnail=30', '-frames:v', '1', '-q:v', '2', $framePath],
        ];

        $nearEnd = $this->representativeTimestamp($duration);
        if ($nearEnd > 0) {
            $commands[] = [...$base, '-ss', number_format($nearEnd, 3, '.', ''), '-frames:v', '1', '-q:v', '2', $framePath];
        }

        $commands[] = [...$base, '-ss', '0.000', '-frames:v', '1', '-q:v', '2', $framePath];

        return $commands;
    }

    private function ffmpegBinary(): string
    {
        return is_string($this->config->ffmpegPath) && $this->config->ffmpegPath !== ''
            ? $this->config->ffmpegPath
            : 'ffmpeg';
    }

    private function timeoutSeconds(): int
    {
        return $this->config->timeoutSeconds > 0 ? $this->config->timeoutSeconds : 60;
    }

    private function isValidJpeg(string $framePath): bool
    {
        if (! is_file($framePath) || filesize($framePath) < 4) {
            return false;
        }

        $header = file_get_contents($framePath, false, null, 0, 2);
        if ($header !== "\xFF\xD8") {
            return false;
        }

        set_error_handler(static fn (): bool => true);
        try {
            $image = imagecreatefromjpeg($framePath);
        } finally {
            restore_error_handler();
        }

        if ($image === false) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $command
     */
    private function runProcess(array $command, int $timeoutSeconds): string
    {
        $process = new Process($command);
        $process->setTimeout($timeoutSeconds);
        try {
            $process->run();
        } catch (Throwable) {
            return $process->getOutput().$process->getErrorOutput();
        }

        return $process->getOutput().$process->getErrorOutput();
    }
}
