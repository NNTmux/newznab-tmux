<?php

declare(strict_types=1);

namespace App\Facades;

use App\Services\TvProcessing\TvProcessingPipeline;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array processRelease(array|object $release, bool $debug = false)
 * @method static void process(string $groupID = '', string $guidChar = '', int|string|null $processTV = '')
 * @method static TvProcessingPipeline addPipe(\App\Services\TvProcessing\Pipes\AbstractTvProviderPipe $pipe)
 * @method static \Illuminate\Support\Collection getPipes()
 * @method static array getStats()
 *
 * @see \App\Services\TvProcessing\TvProcessingPipeline
 */
class TvProcessing extends Facade // @phpstan-ignore missingType.iterableValue, missingType.generics
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return TvProcessingPipeline::class;
    }
}
