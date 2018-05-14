<?php

namespace Blacklight;

use Blacklight\db\DB;

class ReleaseSearch
{
    public const FULLTEXT = 0;
    public const LIKE = 1;
    public const SPHINX = 2;

    /***
     * @var DB
     */
    public $pdo;

    /**
     * Array where keys are the column name, and value is the search string.
     * @var array
     */
    private $searchOptions;

    /**
     * Sets the string to join the releases table to the release search table if using full text.
     * @var string
     */
    private $fullTextJoinString;

    /**
     * @var string
     */
    private $sphinxQueryOpt;

    /**
     * ReleaseSearch constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->fullTextJoinString = 'INNER JOIN releases_se rse ON rse.id = r.id';
        $this->sphinxQueryOpt = ';limit=10000;maxmatches=10000;sort=relevance;mode=extended';
    }

    /**
     * Create part of a SQL query for searching releases.
     *
     * @param array $options   Array where keys are the column name, and value is the search string.
     *
     *
     * @return string|\Illuminate\Database\Query\Builder
     */
    public function getSearchSQL(array $options = [])
    {
        $this->searchOptions = $options;

        return $this->sphinxSQL();
    }

    /**
     * Returns the string for joining the release search table to the releases table.
     * @return string
     */
    public function getFullTextJoinString(): string
    {
        return $this->fullTextJoinString;
    }

    /**
     * @param null $query
     * @param bool $builder
     *
     * @return string
     */
    private function sphinxSQL()
    {
        $searchQuery = $fullReturn = '';

        foreach ($this->searchOptions as $columnName => $searchString) {
            $searchWords = '';
            foreach (explode(' ', $searchString) as $word) {
                $word = str_replace("'", "\\'", trim($word, "\n\t\r\0\x0B "));
                if ($word !== '') {
                    $searchWords .= ($word.' ');
                }
            }
            $searchWords = rtrim($searchWords, "\n\t\r\0\x0B ");
            if ($searchWords !== '') {
                $searchQuery .= sprintf(
                    '@%s %s ',
                    $columnName,
                    $searchWords
                );
            }
        }
        if ($searchQuery !== '') {
            $fullReturn = sprintf("AND (rse.query = '@@relaxed %s')", trim($searchQuery).$this->sphinxQueryOpt);
        }

        return $fullReturn;
    }
}
