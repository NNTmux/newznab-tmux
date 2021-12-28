<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\CategoryRegex;
use App\Models\CollectionRegex;
use App\Models\Release;
use App\Models\ReleaseNamingRegex;
use App\Models\UsenetGroup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Class Regexes.
 */
class Regexes
{
    /**
     * @var mixed The ID of the Regex input string matched or the generic name
     */
    public $matchedRegex;

    /**
     * @var string Name of the current table we are working on.
     */
    public $tableName;

    /**
     * @var array Cache of regex and their TTL.
     */
    protected $_regexCache;

    /**
     * @var int
     */
    protected $_categoriesID = Category::OTHER_MISC;

    /**
     * @param  array  $options
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Settings' => null,
            'Table_Name' => '',
        ];
        $options += $defaults;

        $this->tableName = $options['Table_Name'];
    }

    /**
     * Add a new regex.
     *
     * @param  array  $data
     * @return bool
     */
    public function addRegex(array $data): bool
    {
        return (bool) DB::insert(
            sprintf(
                'INSERT INTO %s (group_regex, regex, status, description, ordinal%s) VALUES (%s, %s, %d, %s, %d%s)',
                $this->tableName,
                ($this->tableName === 'category_regexes' ? ', categories_id' : ''),
                trim(escapeString($data['group_regex'])),
                trim(escapeString($data['regex'])),
                $data['status'],
                trim(escapeString($data['description'])),
                $data['ordinal'],
                ($this->tableName === 'category_regexes' ? (', '.$data['categories_id']) : '')
            )
        );
    }

    /**
     * Update a regex with new info.
     *
     * @param  array  $data
     * @return bool
     */
    public function updateRegex(array $data): bool
    {
        return (bool) DB::update(
            sprintf(
                'UPDATE %s
				SET group_regex = %s, regex = %s, status = %d, description = %s, ordinal = %d %s
				WHERE id = %d',
                $this->tableName,
                trim(escapeString($data['group_regex'])),
                trim(escapeString($data['regex'])),
                $data['status'],
                trim(escapeString($data['description'])),
                $data['ordinal'],
                ($this->tableName === 'category_regexes' ? (', categories_id = '.$data['categories_id']) : ''),
                $data['id']
            )
        );
    }

    /**
     * Get a single regex using its id.
     *
     * @param  int  $id
     * @return array
     */
    public function getRegexByID($id): array
    {
        return (array) Arr::first(DB::select(sprintf('SELECT * FROM %s WHERE id = %d LIMIT 1', $this->tableName, $id)));
    }

    /**
     * @param  string  $group_regex
     * @return mixed
     */
    public function getRegex($group_regex = '')
    {
        if ($this->tableName === 'collection_regexes') {
            $table = CollectionRegex::class;
        } elseif ($this->tableName === 'category_regexes') {
            $table = CategoryRegex::class;
        } else {
            $table = ReleaseNamingRegex::class;
        }

        $result = $table::query();
        if ($group_regex !== '') {
            $result->where('group_regex', 'like', '%'.$group_regex.'%');
        }
        $result->orderBy('id');

        return $result->paginate(config('nntmux.items_per_page'));
    }

    /**
     * Get the count of regex in the DB.
     *
     * @param  string  $group_regex  Optional, keyword to find a group.
     * @return int
     */
    public function getCount($group_regex = ''): int
    {
        $query = DB::select(
            sprintf(
                'SELECT COUNT(id) AS count FROM %s %s',
                $this->tableName,
                $this->_groupQueryString($group_regex)
            )
        );

        return (int) $query[0]->count;
    }

    /**
     * Delete a regex using its id.
     *
     * @param  int  $id
     *
     * @throws \Throwable
     */
    public function deleteRegex($id): void
    {
        DB::transaction(function () use ($id) {
            DB::delete(sprintf('DELETE FROM %s WHERE id = %d', $this->tableName, $id));
        }, 3);
    }

    /**
     * Test a single collection regex for a group name.
     *
     * Requires table per group to be on.
     *
     * @param  string  $groupName
     * @param  string  $regex
     * @param  int  $limit
     * @return array
     *
     * @throws \Exception
     */
    public function testCollectionRegex($groupName, $regex, $limit): array
    {
        $groupID = UsenetGroup::getIDByName($groupName);

        if (! $groupID) {
            return [];
        }

        $rows = DB::select(
            'SELECT
					b.name, b.totalparts, b.currentparts, HEX(b.binaryhash) AS binaryhash,
					c.fromname, c.collectionhash
				FROM binaries b
				INNER JOIN collections c ON c.id = b.collections_id'

        );

        $data = [];
        if (\count($rows) > 0) {
            $limit--;
            $hashes = [];
            foreach ($rows as $row) {
                if (preg_match($regex, $row->name, $hits)) {
                    ksort($hits);
                    $string = $string2 = '';
                    foreach ($hits as $key => $hit) {
                        if (! \is_int($key)) {
                            $string .= $hit;
                            $string2 .= '<br/>'.$key.': '.$hit;
                        }
                    }
                    $files = 0;
                    if (preg_match('/[[(\s](\d{1,5})(\/|[\s_]of[\s_]|-)(\d{1,5})[])\s$:]/i', $row->name, $fileCount)) {
                        $files = $fileCount[3];
                    }
                    $newCollectionHash = sha1($string.$row->fromname.$groupID.$files);
                    $data['New hash: '.$newCollectionHash.$string2][$row->binaryhash] = [
                        'new_collection_hash' => $newCollectionHash,
                        'file_name'           => $row->name,
                        'file_total_parts'    => $row->totalparts,
                        'file_current_parts'  => $row->currentparts,
                        'collection_poster'   => $row->fromname,
                        'old_collection_hash' => $row->collectionhash,
                    ];

                    if ($limit > 0) {
                        if (\count($hashes) > $limit) {
                            break;
                        }
                        $hashes[$newCollectionHash] = '';
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param $groupName
     * @param $regex
     * @param $displayLimit
     * @param $queryLimit
     * @return array
     *
     * @throws \Exception
     */
    public function testReleaseNamingRegex($groupName, $regex, $displayLimit, $queryLimit): array
    {
        $groupID = UsenetGroup::getIDByName($groupName);

        if (! $groupID) {
            return [];
        }

        $rows = Release::query()->where('groups_id', $groupID)->select(['name', 'searchname', 'id']);
        if ((int) $queryLimit !== 0) {
            $rows->limit($queryLimit);
        }

        $rows->get();

        $data = [];
        if ($rows !== null) {
            $limit = 1;
            foreach ($rows as $row) {
                $hit = $this->_matchRegex($regex, $row['name']);
                if ($hit) {
                    $data[$row['id']] = [
                        'subject'  => $row['name'],
                        'old_name' => $row['searchname'],
                        'new_name' => $hit,
                    ];
                    if ((int) $displayLimit > 0 && $limit++ >= (int) $displayLimit) {
                        break;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * This will try to find regex in the DB for a group and a usenet subject, attempt to match them and return the matches.
     *
     * @param  string  $subject
     * @param  string  $groupName
     * @return string
     *
     * @throws \Exception
     */
    public function tryRegex($subject, $groupName): string
    {
        $this->matchedRegex = 0;

        $this->_fetchRegex($groupName);

        $returnString = '';
        // If there are no regex, return and try regex in this file.
        if ($this->_regexCache[$groupName]['regex']) {
            foreach ($this->_regexCache[$groupName]['regex'] as $regex) {
                if ($this->tableName === 'category_regexes') {
                    $this->_categoriesID = $regex->categories_id;
                }

                $returnString = $this->_matchRegex($regex->regex, $subject);
                // If this regex found something, break and return, or else continue trying other regex.
                if ($returnString) {
                    $this->matchedRegex = $regex->id;
                    break;
                }
            }
        }

        return $returnString;
    }

    /**
     * Get the regex from the DB, cache them locally for 15 mins.
     * Cache them also in the cache server, as this script might be terminated.
     *
     * @param  string  $groupName
     */
    protected function _fetchRegex($groupName): void
    {
        // Check if we need to do an initial cache or refresh our cache.
        if (isset($this->_regexCache[$groupName]['ttl']) && (time() - $this->_regexCache[$groupName]['ttl']) < config('nntmux.cache_expiry_long')) {
            return;
        }

        // Get all regex from DB which match the current group name. Cache them for 15 minutes. #CACHEDQUERY#
        $this->_regexCache[$groupName]['regex'] = DB::select(
            sprintf(
                'SELECT r.id, r.regex %s FROM %s r WHERE %s REGEXP r.group_regex AND r.status = 1 ORDER BY r.ordinal ASC, r.group_regex ASC',
                ($this->tableName === 'category_regexes' ? ', r.categories_id' : ''),
                $this->tableName,
                escapeString($groupName)
            )
        );
        // Set the TTL.
        $this->_regexCache[$groupName]['ttl'] = time();
    }

    /**
     * Find matches on a regex taken from the database.
     *
     * Requires at least 1 named captured group.
     *
     * @param  string  $regex
     * @param  string  $subject
     * @return string
     *
     * @throws \Exception
     */
    protected function _matchRegex($regex, $subject): string
    {
        $returnString = '';
        if (preg_match($regex, $subject, $hits) && \count($hits) > 0) {
            // Sort the keys, the named key matches will be concatenated in this order.
            ksort($hits);
            foreach ($hits as $key => $value) {
                switch ($this->tableName) {
                    case 'collection_regexes': // Put this at the top since it's the most important for performance.
                    case 'release_naming_regexes':
                        // Ignore non-named capture groups. Only named capture groups are important.
                        if (\is_int($key) || preg_match('#reqid|parts#i', $key)) {
                            continue 2;
                        }
                        $returnString .= $value; // Concatenate the string to return.
                        break;
                    case 'category_regexes':
                        $returnString = $this->_categoriesID; // Regex matched, so return the category ID.
                        break 2;
                }
            }
        }

        return $returnString;
    }

    /**
     * Format part of a query.
     *
     * @param  string  $group_regex
     * @return string
     */
    protected function _groupQueryString($group_regex): string
    {
        return $group_regex ? ('WHERE group_regex LIKE '.escapeString('%'.$group_regex.'%')) : '';
    }
}
