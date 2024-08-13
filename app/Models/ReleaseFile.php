<?php

namespace App\Models;

use Blacklight\ElasticSearchSiteSearch;
use Blacklight\ManticoreSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * App\Models\ReleaseFile.
 *
 * @property int $releases_id FK to releases.id
 * @property string $name
 * @property int $size
 * @property bool $ishashed
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property bool $passworded
 * @property-read Release $release
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseFile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseFile whereIshashed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseFile whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseFile wherePassworded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseFile whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseFile whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseFile whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 *
 * @property string $crc32
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseFile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseFile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseFile query()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseFile whereCrc32($value)
 */
class ReleaseFile extends Model
{
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var string
     */
    protected $primaryKey = 'releases_id';

    public function release(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }

    /**
     * Get releasefiles row by id.
     *
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getReleaseFiles($id)
    {
        return self::query()->where('releases_id', $id)->orderBy('name')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getByGuid($guid)
    {
        return self::query()
            ->join('releases', 'releases.id', '=', 'release_files.releases_id')
            ->where('releases.guid', $guid)
            ->orderBy('release_files.name')->get();
    }

    /**
     * Add new files for a release ID.
     *
     *
     *
     * @throws \Exception
     */
    public static function addReleaseFiles($id, $name, $size, $createdTime, $hasPassword, string $hash = '', string $crc = ''): int
    {
        // Check if we already have this data in table
        $duplicateCheck = self::query()->where('releases_id', $id)->where('name', $name)->first();

        // Check if the release exists in releases table to prevent foreign key error
        $releaseCheck = Release::query()->where('id', $id)->first();

        if (is_int($createdTime)) {
            if ($createdTime === 0) {
                $adjustedCreatedTime = now()->format('Y-m-d H:i:s');
            } else {
                $adjustedCreatedTime = Carbon::createFromTimestamp($createdTime, date_default_timezone_get())->format('Y-m-d H:i:s');
            }
        } else {
            $adjustedCreatedTime = $createdTime;
        }

        if ($duplicateCheck === null && $releaseCheck !== null) {
            try {
                $insert = self::insertOrIgnore([
                    'releases_id' => $id,
                    'name' => $name,
                    'size' => $size,
                    'created_at' => $adjustedCreatedTime,
                    'updated_at' => now()->timestamp,
                    'passworded' => $hasPassword,
                    'crc32' => $crc,
                ]);
            } catch (\PDOException $e) {
                Log::alert($e->getMessage());
            }

            if (\strlen($hash) === 32) {
                ParHash::insertOrIgnore(['releases_id' => $id, 'hash' => $hash]);
            }
            if (config('nntmux.elasticsearch_enabled') === true) {
                (new ElasticSearchSiteSearch)->updateRelease($id);
            } else {
                (new ManticoreSearch)->updateRelease($id);
            }
        }

        return $insert ?? 0;
    }
}
