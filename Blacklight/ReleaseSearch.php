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
     * @param \Blacklight\db\DB $settings
     *
     * @throws \Exception
     */
    public function __construct(DB $settings)
    {
        $this->fullTextJoinString = 'INNER JOIN releases_se rse ON rse.id = r.id';
        $this->sphinxQueryOpt = ';limit=10000;maxmatches=10000;sort=relevance;mode=extended';
        $this->pdo = $settings instanceof DB ? $settings : new DB();
    }

    /**
     * Create part of a SQL query for searching releases.
     *
     * @param array $options   Array where keys are the column name, and value is the search string.
     * @param bool  $forceLike Force a "like" search on the column.
     *
     * @return string
     */
    public function getSearchSQL(array $options = [], $forceLike = false): string
    {
        $this->searchOptions = $options;

        if ($forceLike) {
            return $this->likeSQL();
        }

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
     * Create SQL sub-query for standard search.
     *
     * @return string
     */
    private function likeSQL(): string
    {
        $return = '';
        foreach ($this->searchOptions as $columnName => $searchString) {
            $wordCount = 0;
            $words = explode(' ', $searchString);
            foreach ($words as $word) {
                if ($word !== '') {
                    $word = trim($word, "-\n\t\r\0\x0B ");
                    if ($wordCount === 0 && (strpos($word, '^') === 0)) {
                        $return .= sprintf(' AND r.%s %s', $columnName, $this->pdo->likeString(substr($word, 1), false));
                    } elseif (strpos($word, '--') === 0) {
                        $return .= sprintf(' AND r.%s NOT %s', $columnName, $this->pdo->likeString(substr($word, 2)));
                    } else {
                        $return .= sprintf(' AND r.%s %s', $columnName, $this->pdo->likeString($word));
                    }
                    $wordCount++;
                }
            }
        }

        return $return;
    }

    /**
     * Create SQL sub-query using sphinx full text search.
     *
     * @return string
     */
    private function sphinxSQL(): string
    {
        $searchQuery = $fullReturn = '';

        foreach ($this->searchOptions as $columnName => $searchString) {
            $searchWords = '';
            $words = explode(' ', $searchString);
            foreach ($words as $word) {
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
        } else {
            $fullReturn = $this->likeSQL();
        }

        return $fullReturn;
    }
}
