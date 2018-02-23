<?php

namespace App\Models;

use Blacklight\ColorCLI;
use Blacklight\ConsoleTools;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * @property mixed $release
 * @property mixed $hash
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
     * @param $dateLimit
     * @throws \RuntimeException
     */
    public static function checkPre($dateLimit = false): void
    {
        $consoleTools = new ConsoleTools();
        $updated = 0;

        if (env('echocli', true)) {
            echo ColorCLI::header('Querying DB for release search names not matched with PreDB titles.');
        }

        $query = self::query()
            ->where('releases.predb_id', '<', 1)
            ->join('releases', 'predb.title', '=', 'releases.searchname')
            ->select(['predb.id as predb_id', 'releases.id as releases_id']);
        if ($dateLimit !== false && is_numeric($dateLimit)) {
            $query->where('adddate', '>', Carbon::now()->subDays($dateLimit));
        }

        $res = $query->get();

        if ($res !== null) {
            $total = \count($res);
            echo ColorCLI::primary(number_format($total).' releases to match.');

            if ($res instanceof \Traversable) {
                foreach ($res as $row) {
                    Release::query()->where('id', $row['releases_id'])->update(['predb_id' => $row['predb_id']]);

                    if (env('echocli', true)) {
                        $consoleTools->overWritePrimary(
                            'Matching up preDB titles with release searchnames: '.$consoleTools->percentString(++$updated, $total)
                        );
                    }
                }
                if (env('echocli', true)) {
                    echo PHP_EOL;
                }
            }

            if (env('echocli', true)) {
                echo ColorCLI::header(
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
     * @return array|bool Array with title/id from PreDB if found, bool False if not found.
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
        $fileCheck = self::query()->where('filename', $cleanerName)->first(['id', 'title']);

        if ($fileCheck !== null) {
            return [
                'title' => $fileCheck['title'],
                'predb_id' => $fileCheck['id'],
            ];
        }

        return false;
    }

    /**
     * Get all PRE's in the DB.
     *
     *
     * @param $offset
     * @param $offset2
     * @param string|array $search
     * @return array
     */
    public static function getAll($offset, $offset2, $search = ''): array
    {
        if ($search !== '') {
            $search = explode(' ', trim($search));
        }

        $expiresAt = Carbon::now()->addSeconds(NN_CACHE_EXPIRY_MEDIUM);
        if ($search === '') {
            $check = Cache::get('predbcount');
            if ($check !== null) {
                $count = $check;
            } else {
                $count = self::count();
                Cache::put('predbcount', $count, $expiresAt);
            }
        } else {
            $sql = self::query()->where(function ($query) use ($search) {
                for ($i = 0, $iMax = \count($search); $i < $iMax; $i++) {
                    $query->where('title', 'like', '%'.$search[$i].'%');
                }
            });
            $check = Cache::get(md5(implode(',', $search)));
            if ($check !== null) {
                $count = $check;
            } else {
                $count = $sql->count('id');
                Cache::put(md5(implode(',', $search)), $count, $expiresAt);
            }
        }

        $sql = self::query()->leftJoin('releases', 'predb.id', '=', 'releases.predb_id')->orderBy('predb.predate', 'desc')->limit($offset2)->offset($offset);
        if ($search !== '') {
            $sql->where(function ($query) use ($search) {
                for ($i = 0, $iMax = \count($search); $i < $iMax; $i++) {
                    $query->where('title', 'like', '%'.$search[$i].'%');
                }
            });
        }
        $search = $search !== '' ? implode(',', $search) : '';
        $check = Cache::get(md5($offset.$offset2.$search));
        if ($check !== null) {
            $parr = $check;
        } else {
            $parr = $sql->get();
            Cache::put(md5($offset.$offset2.$search), $parr, $expiresAt);
        }

        return ['arr' => $parr, 'count' => $count ?? 0];
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
