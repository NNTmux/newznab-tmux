<?php

namespace nntmux;

use nntmux\db\DB;
use Carbon\Carbon;
use App\Models\Release;
use App\Models\Predb as PredbModel;
use Illuminate\Support\Facades\Cache;

/**
 * Class for inserting names/categories etc from PreDB sources into the DB,
 * also for matching names on files / subjects.
 *
 * Class PreDb
 */
class PreDb
{
    // Nuke status.
    public const PRE_NONUKE = 0; // Pre is not nuked.
    public const PRE_UNNUKED = 1; // Pre was un nuked.
    public const PRE_NUKED = 2; // Pre is nuked.
    public const PRE_MODNUKE = 3; // Nuke reason was modified.
    public const PRE_RENUKED = 4; // Pre was re nuked.
    public const PRE_OLDNUKE = 5; // Pre is nuked for being old.

    /**
     * @var
     */
    protected $site;

    /**
     * @var bool
     */
    protected $echooutput;

    /**
     * @var \nntmux\db\DB
     */
    protected $pdo;

    private $dateLimit;

    /**
     * @param array $options
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'     => false,
            'Settings' => null,
        ];
        $options += $defaults;

        $this->echooutput = ($options['Echo'] && NN_ECHOCLI);
        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
    }

    /**
     * Attempts to match PreDB titles to releases.
     *
     * @param $dateLimit
     * @throws \RuntimeException
     */
    public function checkPre($dateLimit = false): void
    {
        $this->dateLimit = $dateLimit;

        $consoleTools = new ConsoleTools();
        $updated = 0;

        if ($this->echooutput) {
            echo ColorCLI::header('Querying DB for release search names not matched with PreDB titles.');
        }

        $query = PredbModel::query()
            ->where('releases.predb_id', '<', 1)
            ->join('releases', 'predb.title', '=', 'releases.searchname')
            ->select(['predb.id as predb_id', 'releases.id as releases_id']);
        if ($this->dateLimit !== false && is_numeric($this->dateLimit)) {
            $query->where('adddate', '>', Carbon::now()->subDays($this->dateLimit));
        }

        $res = $query->get();

        if ($res !== null) {
            $total = \count($res);
            echo ColorCLI::primary(number_format($total).' releases to match.');

            if ($res instanceof \Traversable) {
                foreach ($res as $row) {
                    Release::query()->where('id', $row['releases_id'])->update(['predb_id' => $row['predb_id']]);

                    if ($this->echooutput) {
                        $consoleTools->overWritePrimary(
                            'Matching up preDB titles with release searchnames: '.$consoleTools->percentString(++$updated, $total)
                        );
                    }
                }
                if ($this->echooutput) {
                    echo PHP_EOL;
                }
            }

            if ($this->echooutput) {
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
    public function matchPre($cleanerName)
    {
        if (empty($cleanerName)) {
            return false;
        }

        $titleCheck = PredbModel::query()->where('title', $cleanerName)->first(['id']);

        if ($titleCheck !== null) {
            return [
                'title' => $cleanerName,
                'predb_id' => $titleCheck['id'],
            ];
        }

        // Check if clean name matches a PreDB filename.
        $fileCheck = PredbModel::query()->where('filename', $cleanerName)->first(['id', 'title']);

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
    public function getAll($offset, $offset2, $search = ''): array
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
                $count = PredbModel::count();
                Cache::put('predbcount', $count, $expiresAt);
            }
        } else {
            $sql = PredbModel::query()->where(function ($query) use ($search) {
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

        $sql = PredbModel::query()->leftJoin('releases', 'predb.id', '=', 'releases.predb_id')->orderBy('predb.predate', 'desc')->limit($offset2)->offset($offset);
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
    public function getForRelease($preID)
    {
        return PredbModel::query()->where('id', $preID)->get();
    }

    /**
     * Return a single PRE for a release.
     *
     *
     * @param $preID
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getOne($preID)
    {
        return PredbModel::query()->where('id', $preID)->first();
    }
}
