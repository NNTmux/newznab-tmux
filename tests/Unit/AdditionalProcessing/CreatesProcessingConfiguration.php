<?php

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\Config\ProcessingConfiguration;
use ReflectionClass;
use ReflectionProperty;

trait CreatesProcessingConfiguration
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeConfig(array $overrides = []): ProcessingConfiguration
    {
        $reflection = new ReflectionClass(ProcessingConfiguration::class);
        /** @var ProcessingConfiguration $config */
        $config = $reflection->newInstanceWithoutConstructor();

        $values = array_merge([
            'echoCLI' => false,
            'innerFileBlacklist' => false,
            'maxNestedLevels' => 1,
            'extractUsingRarInfo' => true,
            'fetchLastFiles' => false,
            'unrarPath' => false,
            'unzipPath' => false,
            'timeoutPath' => false,
            'timeoutSeconds' => 0,
            'queryLimit' => 25,
            'segmentsToDownload' => 2,
            'maximumRarSegments' => 3,
            'maximumRarPasswordChecks' => 1,
            'maxSizeGB' => 100,
            'minSizeMB' => 0,
            'alternateNNTP' => false,
            'ffmpegDuration' => 5,
            'addPAR2Files' => false,
            'processVideo' => false,
            'processThumbnails' => false,
            'processAudioSample' => false,
            'processJPGSample' => false,
            'processMediaInfo' => false,
            'processAudioInfo' => false,
            'processPasswords' => false,
            'audioSavePath' => sys_get_temp_dir().'/',
            'tmpUnrarPath' => sys_get_temp_dir().'/',
            'debugMode' => false,
            'searchEnabled' => false,
            'searchDriver' => 'manticore',
            'renameMusicMediaInfo' => false,
            'renamePar2' => false,
            'ffmpegPath' => false,
            'mediaInfoPath' => false,
            'releaseProcessingTimeout' => 0,
            'maxPpTimeoutCount' => 3,
            'audioFileRegex' => '\\.(MP3|FLAC|OGG)',
            'ignoreBookRegex' => '/\\b(epub|pdf)\\b/i',
            'supportFileRegex' => '\\.(?:par2|sfv|nzb)',
            'videoFileRegex' => '\\.(AVI|MKV|MP4)',
        ], $overrides);

        foreach ($values as $property => $value) {
            $prop = new ReflectionProperty(ProcessingConfiguration::class, $property);
            $prop->setValue($config, $value);
        }

        return $config;
    }
}
