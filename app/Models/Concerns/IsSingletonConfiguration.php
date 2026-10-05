<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait IsSingletonConfiguration
{
    public const int SINGLETON_ID = 1;

    public static function singleton(): static
    {
        /** @var static $configuration */
        $configuration = static::query()->findOrFail(self::SINGLETON_ID);

        return $configuration;
    }

    protected static function booted(): void
    {
        static::creating(function (Model $model): void {
            $model->setAttribute($model->getKeyName(), self::SINGLETON_ID);
        });
    }
}
