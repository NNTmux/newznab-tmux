<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\Genre.
 *
 * @property int $id
 * @property string $title
 * @property int|null $type
 * @property bool $disabled
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\MusicInfo[] $music
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre whereDisabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre disabled()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre enabled()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre ofType($type)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Genre query()
 */
class Genre extends Model
{
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    public function music(): HasMany
    {
        return $this->hasMany(MusicInfo::class, 'genres_id');
    }

    public function consoleInfo(): HasMany
    {
        return $this->hasMany(ConsoleInfo::class, 'genres_id');
    }

    public function gamesInfo(): HasMany
    {
        return $this->hasMany(GamesInfo::class, 'genres_id');
    }

    /**
     * Scope to filter disabled genres.
     */
    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('disabled', '=', 1);
    }

    /**
     * Scope to filter enabled genres.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('disabled', '=', 0);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        if (! empty($type)) {
            return $query->where('type', '=', $type);
        }

        return $query;
    }

    /**
     * Get filtered genres based on type and active status.
     */
    public static function getFiltered(string $type = '', bool $activeOnly = false): Collection
    {
        if ($activeOnly) {
            return self::getActiveGenres($type);
        }

        $query = self::query();

        if (! empty($type)) {
            $query->ofType($type);
        }

        return $query->orderBy('title')->get();
    }

    /**
     * Get count of filtered genres.
     */
    public static function getFilteredCount(string $type = '', bool $activeOnly = false): int
    {
        if ($activeOnly) {
            return self::getActiveGenres($type)->count();
        }

        $query = self::query();

        if (! empty($type)) {
            $query->ofType($type);
        }

        return $query->count();
    }

    /**
     * Get genres that are actively used in musicinfo, consoleinfo, or gamesinfo.
     */
    private static function getActiveGenres(string $type = ''): Collection
    {
        $typeSql = ! empty($type) ? ' AND g.type = '.intval($type) : '';

        $sql = sprintf(
            'SELECT g.*
            FROM genres g
            INNER JOIN (SELECT DISTINCT genres_id FROM musicinfo) x ON x.genres_id = g.id WHERE 1=1 %1$s
            UNION
            SELECT g.*
            FROM genres g
            INNER JOIN (SELECT DISTINCT genres_id FROM consoleinfo) x ON x.genres_id = g.id WHERE 1=1 %1$s
            UNION
            SELECT g.*
            FROM genres g
            INNER JOIN (SELECT DISTINCT genres_id FROM gamesinfo) x ON x.genres_id = g.id WHERE 1=1 %1$s
            ORDER BY title',
            $typeSql
        );

        $results = DB::select($sql);

        return self::hydrate($results);
    }
}
