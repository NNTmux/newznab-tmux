<?php

declare(strict_types=1);

namespace App\Services\IGDB\Models;

use App\Services\IGDB\DataNode;
use App\Services\IGDB\QueryBuilder;

abstract class BaseModel extends DataNode
{
    protected const string ENDPOINT = '';

    /**
     * @var array<int, string>
     */
    protected const array DATE_FIELDS = [
        'created_at',
        'updated_at',
        'change_date',
        'start_date',
        'published_at',
        'first_release_date',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes, static::DATE_FIELDS);
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class);
    }

    public static function endpoint(): string
    {
        return static::ENDPOINT;
    }

    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::query()->{$method}(...$parameters);
    }
}
