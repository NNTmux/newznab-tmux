<?php

namespace nntmux;

use nntmux\db\DB;

class SphinxSearch
{
    /**
     * SphinxQL connection.
     * @var DB
     */
    public $sphinxQL = null;

    /**
     * Establish connection to SphinxQL.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        if (NN_RELEASE_SEARCH_TYPE === ReleaseSearch::SPHINX) {
            if (! defined('NN_SPHINXQL_HOST_NAME')) {
                define('NN_SPHINXQL_HOST_NAME', '0');
            }
            if (! defined('NN_SPHINXQL_PORT')) {
                define('NN_SPHINXQL_PORT', 9306);
            }
            if (! defined('NN_SPHINXQL_SOCK_FILE')) {
                define('NN_SPHINXQL_SOCK_FILE', '');
            }
            $this->sphinxQL = new DB(
                [
                    'dbname' => '',
                    'dbport' => NN_SPHINXQL_PORT,
                    'dbhost' => NN_SPHINXQL_HOST_NAME,
                    'dbsock' => NN_SPHINXQL_SOCK_FILE,
                ]
            );
        }
    }

    /**
     * Insert release into Sphinx RT table.
     * @param $parameters
     */
    public function insertRelease($parameters): void
    {
        if ($this->sphinxQL !== null && $parameters['id']) {
            $this->sphinxQL->queryExec(
                sprintf(
                    'REPLACE INTO releases_rt (id, name, searchname, fromname, filename) VALUES (%d, %s, %s, %s, %s)',
                    $parameters['id'],
                    $this->escapeString($parameters['name']),
                    $this->escapeString($parameters['searchname']),
                    $this->escapeString($parameters['fromname']),
                    empty($parameters['filename']) ? "''" : $this->escapeString($parameters['filename'])
                )
            );
        }
    }

    /**
     * Delete release from Sphinx RT tables.
     * @param array $identifiers ['g' => Release GUID(mandatory), 'id' => ReleaseID(optional, pass false)]
     * @param DB $pdo
     */
    public function deleteRelease($identifiers, DB $pdo): void
    {
        if ($this->sphinxQL !== null) {
            if ($identifiers['i'] === false) {
                $identifiers['i'] = $pdo->queryOneRow(
                    sprintf('SELECT id FROM releases WHERE guid = %s', $pdo->escapeString($identifiers['g']))
                );
                if ($identifiers['i'] !== false) {
                    $identifiers['i'] = $identifiers['i']['id'];
                }
            }
            if ($identifiers['i'] !== false) {
                $this->sphinxQL->queryExec(sprintf('DELETE FROM releases_rt WHERE id = %d', $identifiers['i']));
            }
        }
    }

    /**
     * Escapes characters that are treated as special operators by the query language parser
     *
     * @param string $string unescaped string
     *
     * @return string Escaped string.
     */
    public function escapeString($string): string
    {
        $from = ['\\', '(', ')', '|', '-', '!', '@', '~', '"', '&', '/', '^', '$', '='];
        $to   = ['\\\\', '\(', '\)', '\|', '\-', '\!', '\@', '\~', '\"', '\&', '\/', '\^', '\$', '\='];

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
        if ($this->sphinxQL !== null) {
            $pdo = new DB();
            $new = $pdo->queryOneRow(
                        sprintf(
                            '
							SELECT r.id, r.name, r.searchname, r.fromname, IFNULL(GROUP_CONCAT(rf.name SEPARATOR " "),"") filename
							FROM releases r
							LEFT JOIN release_files rf ON (r.id=rf.releases_id)
							WHERE r.id = %d
							GROUP BY r.id LIMIT 1',
                            $releaseID
                        )
            );
            if ($new !== false) {
                $this->insertRelease($new);
            }
        }
    }

    /**
     * Truncate a RT index.
     * @param string $indexName
     */
    public function truncateRTIndex($indexName): void
    {
        if ($this->sphinxQL !== null) {
            $this->sphinxQL->queryExec(sprintf('TRUNCATE RTINDEX %s', $indexName));
        }
    }

    /**
     * Optimize a RT index.
     * @param string $indexName
     */
    public function optimizeRTIndex($indexName): void
    {
        if ($this->sphinxQL !== null) {
            $this->sphinxQL->queryExec(sprintf('FLUSH RTINDEX %s', $indexName));
            $this->sphinxQL->queryExec(sprintf('OPTIMIZE INDEX %s', $indexName));
        }
    }
}
