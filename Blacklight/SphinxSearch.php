<?php

namespace Blacklight;

use App\Models\Predb;
use App\Models\Release;
use Foolz\SphinxQL\Drivers\Pdo\Connection;
use Foolz\SphinxQL\Exception\DatabaseException;
use Foolz\SphinxQL\Exception\SphinxQLException;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use Illuminate\Support\Facades\DB;

/**
 * Class SphinxSearch.
 */
class SphinxSearch
{
    /**
     * @var \Foolz\SphinxQL\SphinxQL
     */
    public $sphinxQL;

    /**
     * @var \Foolz\SphinxQL\Drivers\Pdo\Connection
     */
    protected $connection;

    /**
     * @var \Illuminate\Config\Repository|mixed
     */
    protected $config;

    /**
     * @var \Foolz\SphinxQL\Helper
     */
    protected $helper;

    /**
     * @var \Blacklight\ColorCLI
     */
    private $cli;

    /**
     * Establish connection to SphinxQL.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->connection = new Connection();
        $this->config = config('sphinxsearch');
        $this->connection->setParams(['host' => $this->config['host'], 'port' => $this->config['port']]);
        $this->sphinxQL = new SphinxQL($this->connection);
        $this->helper = new Helper($this->connection);
        $this->cli = new ColorCLI();
    }

    /**
     * Insert release into Sphinx RT table.
     *
     * @param $parameters
     *
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public function insertRelease($parameters): void
    {
        if ($this->sphinxQL !== null && $parameters['id']) {
            $this->sphinxQL
                ->replace()
                ->into($this->config['indexes']['releases'])
                ->set(['id' => $parameters['id'], 'name' => $parameters['name'], 'searchname' => $parameters['searchname'], 'fromname' => $parameters['fromname'], 'filename' => empty($parameters['filename']) ? "''" : $parameters['filename']])
                ->execute();
        }
    }

    /**
     * Insert release into Sphinx RT table.
     *
     * @param $parameters
     *
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public function insertPredb($parameters): void
    {
        if ($this->sphinxQL !== null && $parameters['id']) {
            $this->sphinxQL
                ->replace()
                ->into($this->config['indexes']['predb'])
                ->set(['id' => $parameters['id'], 'title' => $parameters['title'], 'filename' => empty($parameters['filename']) ? "''" : $parameters['filename'], 'source' => $parameters['source']])
                ->execute();
        }
    }

    /**
     * Delete release from Sphinx RT tables.
     *
     * @param  array  $identifiers  ['g' => Release GUID(mandatory), 'id' => ReleaseID(optional, pass false)]
     */
    public function deleteRelease($identifiers): void
    {
        if ($identifiers['i'] === false) {
            $identifiers['i'] = Release::query()->where('guid', $identifiers['g'])->first(['id']);
            if ($identifiers['i'] !== null) {
                $identifiers['i'] = $identifiers['i']['id'];
            }
        }
        if ($identifiers['i'] !== false) {
            $this->sphinxQL->delete()->from([$this->config['indexes']['releases']])->where('id', '=', $identifiers['i']);
        }
    }

    /**
     * Escapes characters that are treated as special operators by the query language parser.
     *
     * @param  string  $string  unescaped string
     * @return string Escaped string.
     */
    public static function escapeString($string): string
    {
        $from = ['\\', '(', ')', '|', '-', '!', '@', '~', '"', '&', '/', '^', '$', '=', "'"];
        $to = ['\\\\', '\(', '\)', '\|', '\-', '\!', '\@', '\~', '\"', '\&', '\/', '\^', '\$', '\=', "\'"];

        return str_replace($from, $to, $string);
    }

    /**
     * Update Sphinx Relases index for given releases_id.
     *
     * @param  int  $releaseID
     *
     * @throws \Exception
     */
    public function updateRelease($releaseID): void
    {
        $new = Release::query()
                ->where('releases.id', $releaseID)
                ->leftJoin('release_files as rf', 'releases.id', '=', 'rf.releases_id')
                ->select(['releases.id', 'releases.name', 'releases.searchname', 'releases.fromname', DB::raw('IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename')])
                ->groupBy('releases.id')
                ->first();

        if ($new !== null) {
            $this->insertRelease($new);
        }
    }

    /**
     * Update Sphinx Predb index for given predb_id.
     *
     * @param  array  $parameters
     *
     * @throws \Exception
     */
    public function updatePreDb($parameters): void
    {
        if (! empty($parameters)) {
            $this->insertPredb($parameters);
        }
    }

    /**
     * Truncate the RT index.
     *
     * @param  array  $indexes
     * @return bool
     */
    public function truncateRTIndex($indexes = []): bool
    {
        if (empty($indexes)) {
            $this->cli->error('You need to provide index name to truncate');

            return false;
        }
        foreach ($indexes as $index) {
            if (\in_array($index, $this->config['indexes'], true)) {
                $this->helper->truncateRtIndex($index);
                $this->cli->info('Truncating index '.$index.' finished.');
            } else {
                $this->cli->error('Unsupported index: '.$index);
            }
        }

        return true;
    }

    /**
     * Optimize the RT index.
     */
    public function optimizeRTIndex(): void
    {
        foreach ($this->config['indexes'] as $index) {
            $this->helper->flushRtIndex($index);
            $this->helper->optimizeIndex($index);
        }
    }

    /**
     * @param  string  $rt_index  (releases_rt or predb_rt)
     * @param  string  $searchString  (what are we looking for?)
     * @param  array  $column  (one or multiple columns from the columns that exist in indexes)
     * @param  array  $searchArray
     * @return array
     *
     * @throws \Foolz\SphinxQL\Exception\ConnectionException
     * @throws \Foolz\SphinxQL\Exception\DatabaseException
     * @throws \Foolz\SphinxQL\Exception\SphinxQLException
     */
    public function searchIndexes(string $rt_index, $searchString = '', $column = [], array $searchArray = []): array
    {
        $query = $this->sphinxQL->select()->from($rt_index)->option('max_matches', 10000)->option('ranker', 'sph04')->option('sort_method', 'pq')->limit(0, 10000)->orderBy('id', 'desc');
        if (! empty($searchArray)) {
            foreach ($searchArray as $key => $value) {
                $query->match($key, $value, true);
            }
        } elseif (! empty($searchString)) {
            $query->match($column, $searchString, true);
        } else {
            return [];
        }

        try {
            return $query->execute()->fetchAllAssoc() ?? [];
        } catch (SphinxQLException $exception) {
            return [];
        } catch (DatabaseException $databaseException) {
            return [];
        }
    }
}
