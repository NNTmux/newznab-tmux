<?php

namespace App\Facades;

use App\Services\Categorization\CategorizationPipeline;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array categorize(int|string $groupId, string $releaseName, ?string $poster = '', bool $debug = false)
 * @method static \Illuminate\Support\Collection getCategorizers()
 * @method static CategorizationPipeline addCategorizer(\App\Services\Categorization\Contracts\CategorizerInterface $categorizer)
 *
 * @see \App\Services\Categorization\CategorizationPipeline
 */
class Categorization extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CategorizationPipeline::class;
    }
}

