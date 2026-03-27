<?php

declare(strict_types=1);

namespace App\Facades;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Facade;

/**
 * Elasticsearch Facade - provides static access to Elasticsearch client.
 *
 * @method static \Elastic\Elasticsearch\Endpoints\Indices indices()
 * @method static \Elastic\Elasticsearch\Endpoints\Cluster cluster()
 * @method static \Elastic\Elasticsearch\Response\Elasticsearch search(array $params)
 * @method static \Elastic\Elasticsearch\Response\Elasticsearch bulk(array $params)
 * @method static \Elastic\Elasticsearch\Response\Elasticsearch get(array $params)
 * @method static \Elastic\Elasticsearch\Response\Elasticsearch deleteByQuery(array $params)
 *
 * @see Client
 */
class Elasticsearch extends Facade // @phpstan-ignore missingType.iterableValue
{
    protected static function getFacadeAccessor(): string
    {
        return 'elasticsearch';
    }
}
