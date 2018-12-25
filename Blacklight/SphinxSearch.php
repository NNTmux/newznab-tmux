<?php

namespace Blacklight;

use App\Models\Predb;
use App\Models\Release;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use Illuminate\Support\Facades\DB;
use Foolz\SphinxQL\Drivers\Pdo\Connection;

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
    }

    /**
     * Insert release into Sphinx RT table.
     * @param $parameters
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
     * @param $parameters
     */
    public function insertPredb($parameters): void
    {
        if ($this->sphinxQL !== null && $parameters['id']) {
            $this->sphinxQL
                ->replace()
                ->into($this->config['indexes']['predb'])
                ->set(['id' => $parameters['id'], 'title' => $parameters['title'], 'filename' => empty($parameters['filename']) ? "''" : $parameters['filename']])
                ->execute();
        }
    }

    /**
     * Delete release from Sphinx RT tables.
     * @param array $identifiers ['g' => Release GUID(mandatory), 'id' => ReleaseID(optional, pass false)]
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
     * @param string $string unescaped string
     *
     * @return string Escaped string.
     */
    public static function escapeString($string): string
    {
        $from = ['\\', '(', ')', '|', '-', '!', '@', '~', '"', '&', '/', '^', '$', '='];
        $to = ['\\\\', '\(', '\)', '\|', '\-', '\!', '\@', '\~', '\"', '\&', '\/', '\^', '\$', '\='];

        return str_replace($from, $to, $string);
    }

    /**
     * Update Sphinx Relases index for given releases_id.
     *
     * @param int $releaseID
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
     * @param int $title
     * @throws \Exception
     */
    public function updatePreDb($title): void
    {
        $new = Predb::query()
            ->where('title', $title)
            ->select(['id', 'title', 'filename'])
            ->groupBy('id')
            ->first();

        if ($new !== null) {
            $this->insertPredb($new);
        }
    }

    /**
     * Truncate the RT index.
     */
    public function truncateRTIndex(): void
    {
        $this->helper->truncateRtIndex($this->config['indexes']['releases']);
        $this->helper->truncateRtIndex($this->config['indexes']['predb']);
    }

    /**
     * Optimize the RT index.
     */
    public function optimizeRTIndex(): void
    {
        $this->helper->flushRtIndex($this->config['indexes']['releases']);
        $this->helper->optimizeIndex($this->config['indexes']['releases']);
        $this->helper->flushRtIndex($this->config['indexes']['predb']);
        $this->helper->optimizeIndex($this->config['indexes']['predb']);
    }

    /**
     * @param string $searchString (what are we looking for?)
     * @param string|array $column (one or multiple columns from the columns that exist in indexes)
     * @param string $rt_index (releases_rt or predb_rt)
     * @return array
     */
    public function searchIndexes(string $searchString, $column, string $rt_index): array
    {
        return $this->sphinxQL->select()->from($rt_index)->match($column, $searchString)->option('max_matches', 10000)->execute()->fetchAllAssoc() ?? [];
    }
}
