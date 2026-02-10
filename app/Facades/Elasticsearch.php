<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Elasticsearch Facade - provides static access to Elasticsearch client.
 *
 * @method static \Elasticsearch\Namespaces\IndicesNamespace indices()
 * @method static \Elasticsearch\Namespaces\ClusterNamespace cluster()
 * @method static array search(array $params)
 * @method static array bulk(array $params)
 * @method static array get(array $params)
 * @method static array deleteByQuery(array $params)
 *
 * @see \Mailerlite\LaravelElasticsearch\Manager
 */
class Elasticsearch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'elasticsearch';
    }
}
