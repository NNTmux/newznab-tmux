<?php

namespace nntmux;

use App\Models\Release;
use Foolz\SphinxQL\Drivers\Pdo\Connection;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;

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
    protected $index;

    /**
     * Establish connection to SphinxQL.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->connection = new Connection();
        $this->connection->setParams(['host' => config('sphinxsearch.host'), 'port' => config('sphinxsearch.port')]);
        $this->sphinxQL = SphinxQL::create($this->connection);
        $this->index = config('sphinxsearch.index');
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
                ->into($this->index)
                ->set(['id' => $parameters['id'], 'name' => $parameters['name'], 'searchname' => $parameters['searchname'], 'fromname' => $parameters['fromname'], 'filename' => empty($parameters['filename']) ? "''" : $parameters['filename']])
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
            $this->sphinxQL->delete()->from([$this->index])->where('id', '=', $identifiers['i']);
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
                ->select(['releases.id', 'releases.name', 'releases.searchname', 'releases.fromname'])
                ->selectRaw('IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename')
                ->groupBy('releases.id')
                ->first();

        if ($new !== null) {
            $this->insertRelease($new);
        }
    }

    /**
     * Truncate the RT index.
     */
    public function truncateRTIndex(): void
    {
        Helper::create($this->connection)->truncateRtIndex($this->index);
    }

    /**
     * Optimize the RT index.
     */
    public function optimizeRTIndex(): void
    {
        Helper::create($this->connection)->flushRtIndex($this->index);
        Helper::create($this->connection)->optimizeIndex($this->index);
    }
}
