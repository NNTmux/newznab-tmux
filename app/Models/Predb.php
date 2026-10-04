<?php

declare(strict_types=1);

namespace App\Models;

use App\Facades\Search;
use App\Support\PredbSearchDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * App\Models\Predb.
 *
 * @property mixed $release
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
 *
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
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Predb query()
 */
class Predb extends Model
{
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

    protected $dateFormat = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return HasMany<Release, $this>
     */
    public function release(): HasMany
    {
        return $this->hasMany(Release::class, 'predb_id');
    }

    /**
     * Attempts to match PreDB titles to releases.
     *
     *
     * @throws \RuntimeException
     */
    public static function checkPre(bool|int|string $dateLimit = false): void
    {
        $updated = 0;

        if (config('nntmux.echocli')) {
            cli()->header('Querying DB for release search names not matched with PreDB titles.');
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
            cli()->primary(number_format($total).' releases to match.');

            foreach ($res as $row) {
                Release::query()->where('id', $row['releases_id'])->update(['predb_id' => $row['predb_id']]);

                if (config('nntmux.echocli')) {
                    cli()->overWritePrimary(
                        'Matching up preDB titles with release searchnames: '.cli()->percentString(++$updated, $total)
                    );
                }
            }
            if (config('nntmux.echocli')) {
                echo PHP_EOL;
            }

            if (config('nntmux.echocli')) {
                cli()->header(
                    'Matched '.number_format(($updated > 0) ? $updated : 0).' PreDB titles to release search names.'
                );
            }
        }
    }

    /**
     * Try to match a single release to a PreDB title when the release is created.
     *
     * @return array{title: string, predb_id: int}|false
     */
    public static function matchPre(string $cleanerName): array|false
    {
        if (trim($cleanerName) === '') {
            return false;
        }

        try {
            $hit = Search::matchPredbExact($cleanerName);
        } catch (\Throwable $exception) {
            Log::warning('PreDB exact lookup failed', ['exception_class' => $exception::class]);

            return false;
        }

        if ($hit === null || $hit['id'] <= 0) {
            return false;
        }

        $pre = self::query()->find($hit['id'], ['id', 'title', 'filename']);
        if ($pre === null || $pre->title === '') {
            return false;
        }

        $name = PredbSearchDocument::exactValue($cleanerName);
        if (PredbSearchDocument::exactValue($pre->title) !== $name
            && PredbSearchDocument::exactValue($pre->filename) !== $name) {
            return false;
        }

        return ['title' => $pre->title, 'predb_id' => (int) $pre->id];
    }

    /**
     * @return mixed
     *
     * @throws \Exception
     */
    public static function getAll(string $search = '')
    {
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $predb = Cache::get(md5($search));
        if ($predb !== null) {
            return $predb;
        }
        $sql = self::query()
            ->leftJoin('releases', 'releases.predb_id', '=', 'predb.id')
            ->select('predb.*', 'releases.guid')
            ->orderByDesc('predb.predate');
        if (! empty($search)) {
            $ids = Search::searchPredb($search);
            $sql->whereIn('predb.id', $ids);
        }

        $predb = $sql->paginate(config('nntmux.items_per_page'));
        $predb->withPath(url('admin/predb'));
        Cache::put(md5($search), $predb, $expiresAt);

        return $predb;
    }

    /**
     * Get all PRE's for a release.
     */
    public static function getForRelease(mixed $preID): mixed
    {
        return self::query()->where('id', $preID)->get();
    }

    /**
     * Return a single PRE for a release.
     *
     *
     * @return Model|null|static
     */
    public static function getOne(mixed $preID)
    {
        return self::query()->where('id', $preID)->first();
    }
}
