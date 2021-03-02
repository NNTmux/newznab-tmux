<?php

namespace App\Models;

use Blacklight\ColorCLI;
use Blacklight\ConsoleTools;
use Blacklight\ElasticSearchSiteSearch;
use Blacklight\SphinxSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

/**
 * App\Models\Predb.
 *
 * @property mixed $release
 * @property mixed $hash
 * @property int $id Primary key
 * @property string $title
 * @property string|null $nfo
 * @property string|null $size
 * @property string|null $category
 * @property string|null $predate
 * @property string $source
 * @property int $requestid
 * @property int $groups_id FK to groups
 * @property bool $nuked Is this pre nuked? 0 no 2 yes 1 un nuked 3 mod nuked
 * @property string|null $nukereason If this pre is nuked, what is the reason?
 * @property string|null $files How many files does this pre have ?
 * @property string $filename
 * @property bool $searched
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereFiles($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereGroupsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereNfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereNuked($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereNukereason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb wherePredate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereRequestid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereSearched($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb whereTitle($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb query()
 */
class Predb extends Model
{
    use Searchable;

    // Nuke status.
    public const PRE_NONUKE = 0; // Pre is not nuked.
    public const PRE_UNNUKED = 1; // Pre was un nuked.
    public const PRE_NUKED = 2; // Pre is nuked.
    public const PRE_MODNUKE = 3; // Nuke reason was modified.
    public const PRE_RENUKED = 4; // Pre was re nuked.
    public const PRE_OLDNUKE = 5; // Pre is nuked for being old.

    /**
     * @var string
     */
    protected $table = 'predb';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    public function hash()
    {
        return $this->hasMany(PredbHash::class, 'predb_id');
    }

    public function release()
    {
        return $this->hasMany(Release::class, 'predb_id');
    }

    /**
     * Attempts to match PreDB titles to releases.
     *
     * @param string|int|bool $dateLimit
     * @throws \RuntimeException
     */
    public static function checkPre($dateLimit = false): void
    {
        $consoleTools = new ConsoleTools();
        $updated = 0;

        if (config('nntmux.echocli')) {
            (new ColorCLI())->header('Querying DB for release search names not matched with PreDB titles.');
        }

        $query = self::query()
            ->where('releases.predb_id', '<', 1)
            ->join('releases', 'predb.title', '=', 'releases.searchname')
            ->select(['predb.id as predb_id', 'releases.id as releases_id']);
        if ($dateLimit !== false && (int) $dateLimit > 0) {
            $query->where('adddate', '>', now()->subDays((int) $dateLimit));
        }

        $res = $query->get();

        if ($res !== null) {
            $total = \count($res);
            (new ColorCLI())->primary(number_format($total).' releases to match.');

            foreach ($res as $row) {
                Release::query()->where('id', $row['releases_id'])->update(['predb_id' => $row['predb_id']]);

                if (config('nntmux.echocli')) {
                    $consoleTools->overWritePrimary(
                        'Matching up preDB titles with release searchnames: '.$consoleTools->percentString(++$updated, $total)
                        );
                }
            }
            if (config('nntmux.echocli')) {
                echo PHP_EOL;
            }

            if (config('nntmux.echocli')) {
                (new ColorCLI())->header(
                    'Matched '.number_format(($updated > 0) ? $updated : 0).' PreDB titles to release search names.'
                );
            }
        }
    }

    /**
     * Try to match a single release to a PreDB title when the release is created.
     *
     * @param string $cleanerName
     *
     * @return array|false Array with title/id from PreDB if found, false if not found.
     */
    public static function matchPre($cleanerName)
    {
        if (empty($cleanerName)) {
            return false;
        }

        $titleCheck = self::query()->where('title', $cleanerName)->first(['id']);

        if ($titleCheck !== null) {
            return [
                'title' => $cleanerName,
                'predb_id' => $titleCheck['id'],
            ];
        }

        // Check if clean name matches a PreDB filename.
        $fileCheck = self::query()->where('filename_crc', dechex(crc32($cleanerName)))->where('filename', $cleanerName)->first(['id', 'title']);

        if ($fileCheck !== null) {
            return [
                'title' => $fileCheck['title'],
                'predb_id' => $fileCheck['id'],
            ];
        }

        return false;
    }

    /**
     * @param string $search
     *
     * @return mixed
     * @throws \Exception
     */
    public static function getAll($search = '')
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $predb = Cache::get(md5($search));
        if ($predb !== null) {
            return $predb;
        }
        $sql = self::query()->leftJoin('releases', 'releases.predb_id', '=', 'predb.id')->orderByDesc('predb.predate');
        if (! empty($search)) {
            if (config('nntmux.elasticsearch_enabled') === true) {
                $ids = (new ElasticSearchSiteSearch())->predbIndexSearch($search);
            } else {
                $sphinx = new SphinxSearch();
                $ids = Arr::pluck($sphinx->searchIndexes('predb_rt', $search, ['title']), 'id');
            }
            $sql->whereIn('predb.id', $ids);
        }

        $predb = $sql->paginate(config('nntmux.items_per_page'));
        Cache::put(md5($search), $predb, $expiresAt);

        return $predb;
    }

    /**
     * Get all PRE's for a release.
     *
     *
     * @param $preID
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getForRelease($preID)
    {
        return self::query()->where('id', $preID)->get();
    }

    /**
     * Return a single PRE for a release.
     *
     *
     * @param $preID
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getOne($preID)
    {
        return self::query()->where('id', $preID)->first();
    }

    /**
     * @return string
     */
    public function searchableAs()
    {
        return 'ft_predb_filename';
    }

    /**
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'filename' => $this->filename,
        ];
    }
}
