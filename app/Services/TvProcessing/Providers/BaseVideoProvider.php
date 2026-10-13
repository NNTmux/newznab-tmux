<?php

declare(strict_types=1);

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program (see LICENSE.txt in the base directory.  If
 * not, see:
 *
 * @link      <http://www.gnu.org/licenses/>.
 *
 * @author    niel
 * @copyright 2015 nZEDb
 */

namespace App\Services\TvProcessing\Providers;

use App\Models\TvInfo;
use App\Models\Video;
use App\Models\VideoAlias;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Parent class for TV/Film and any similar classes to inherit from.
 */
abstract class BaseVideoProvider
{
    // Video Type Identifiers
    public const TYPE_TV = 0; // Type of video is a TV Programme/Show

    public const TYPE_FILM = 1; // Type of video is a Film/Movie

    public const TYPE_ANIME = 2; // Type of video is a Anime

    public bool $echooutput;

    /**
     * @var list<string> The sites that we have an ID columns for in our video table.
     */
    private static array $sites = ['imdb', 'tmdb', 'trakt', 'tvdb', 'tvmaze', 'tvrage'];

    public function __construct()
    {
        $this->echooutput = (bool) config('nntmux.echocli');
    }

    /**
     * @return false|mixed
     */
    public function getSiteIDFromVideoID(string $siteColumn, int $videoID): mixed
    {
        if (\in_array($siteColumn, self::$sites, true)) {
            $result = Video::query()->where('id', $videoID)->first([$siteColumn]);

            return $result !== null ? $result[$siteColumn] : false;
        }

        return false;
    }

    /**
     * Get TV show local timezone from a Video ID.
     *
     * @return string Empty string if no query return or tz style timezone
     */
    public function getLocalZoneFromVideoID(int $videoID): string
    {
        $zone = trim((string) TvInfo::query()->where('videos_id', $videoID)->value('localzone'));

        return $zone === "''" ? '' : $zone;
    }

    /**
     * Get video info from a Site ID and column.
     */
    protected function getVideoIDFromSiteID(string $siteColumn, int $siteID): bool|int
    {
        if ($siteID === 0 || ! \in_array($siteColumn, self::$sites, true)) {
            return false;
        }

        $videoId = Video::query()->where($siteColumn, $siteID)->orderBy('id')->value('id');

        return $videoId !== null ? (int) $videoId : false;
    }

    public function getByTitle(string $title, int $type, int $source = 0): int
    {
        // Check if we already have an entry for this show.
        $res = $this->getTitleExact($title, $type, $source);
        if ($res !== 0) {
            return $res;
        }

        // If title contains a year in parentheses, try without the year
        if (preg_match('/^(.+?)\s*\(\d{4}\)$/', $title, $yearMatch)) {
            $titleWithoutYear = trim($yearMatch[1]);
            $res = $this->getTitleExact($titleWithoutYear, $type, $source);
            if ($res !== 0) {
                return $res;
            }
        }

        // Check alt. title (Strip ' and :) Maybe strip more in the future.
        $res = $this->getAlternativeTitleExact($title, $type, $source);
        if ($res !== 0) {
            return $res;
        }

        $title2 = str_ireplace(' and ', ' & ', $title);
        if ($title !== $title2) {
            $res = $this->getTitleExact($title2, $type, $source);
            if ($res !== 0) {
                return $res;
            }
            $res = $this->getTitleLoose($this->loosePattern($title2), $type, $source);
            if ($res !== 0) {
                return $res;
            }
        }

        // Some words are spelled correctly 2 ways
        // example theatre and theater
        $title2 = str_ireplace('er', 're', $title);
        if ($title !== $title2) {
            $res = $this->getTitleExact($title2, $type, $source);
            if ($res !== 0) {
                return $res;
            }
            $res = $this->getTitleLoose($this->loosePattern($title2), $type, $source);
            if ($res !== 0) {
                return $res;
            }
        } elseif (\count(explode(' ', $title)) > 1) {
            // If there was not an exact title match, look for title with missing chars
            // example release name :Zorro 1990, tvrage name Zorro (1990)
            // Only search if the title contains more than one word to prevent incorrect matches
            $res = $this->getTitleLoose($this->loosePattern($title), $type, $source);
            if ($res !== 0) {
                return $res;
            }
        }

        return 0;
    }

    private function loosePattern(string $title): string
    {
        $pattern = '%';
        foreach (explode(' ', $title) as $piece) {
            $pattern .= str_ireplace(["'", '!'], '', $piece).'%';
        }

        return $pattern;
    }

    public function getTitleExact(string $title, int $type, int $source = 0): int
    {
        if ($title === '') {
            return 0;
        }

        $videoId = Video::query()
            ->where(['title' => $title, 'type' => $type])
            ->when($source > 0, fn (Builder $query) => $query->where('source', $source))
            ->orderBy('id')
            ->value('id');

        if ($videoId === null) {
            $videoId = Video::query()
                ->join('videos_aliases', 'videos.id', '=', 'videos_aliases.videos_id')
                ->where(['videos_aliases.title' => $title, 'videos.type' => $type])
                ->when($source > 0, fn (Builder $query) => $query->where('videos.source', $source))
                ->orderBy('videos.id')
                ->value('videos.id');
        }

        return (int) $videoId;
    }

    /**
     * Match a LIKE pattern against titles and aliases, preferring the shortest (tightest) title.
     */
    public function getTitleLoose(mixed $title, mixed $type, int $source = 0): int
    {
        $pattern = rtrim((string) $title, '%');
        if ($pattern === '') {
            return 0;
        }

        $videoId = Video::query()
            ->where('title', 'like', $pattern)
            ->where('type', $type)
            ->when($source > 0, fn (Builder $query) => $query->where('source', $source))
            ->orderByRaw('LENGTH(title)')
            ->value('id');

        if ($videoId === null) {
            $videoId = Video::query()
                ->join('videos_aliases', 'videos.id', '=', 'videos_aliases.videos_id')
                ->where('videos_aliases.title', 'like', $pattern)
                ->where('videos.type', $type)
                ->when($source > 0, fn (Builder $query) => $query->where('videos.source', $source))
                ->orderByRaw('LENGTH(videos_aliases.title)')
                ->value('videos.id');
        }

        return (int) $videoId;
    }

    /**
     * Match a title against stored titles with apostrophes or colons removed.
     */
    public function getAlternativeTitleExact(string $title, int $type, int $source = 0): int
    {
        if ($title === '') {
            return 0;
        }

        return (int) Video::query()
            ->where('type', $type)
            ->when($source > 0, fn (Builder $query) => $query->where('source', $source))
            ->where(fn (Builder $query) => $query
                ->whereRaw("REPLACE(title, '''', '') = ?", [$title])
                ->orWhereRaw("REPLACE(title, ':', '') = ?", [$title]))
            ->orderBy('id')
            ->value('id');
    }

    /**
     * Inserts the aliases a video doesn't have yet.
     *
     * @param  array<int|string, mixed>  $aliases  Strings, or TVMaze style ['name' => ...] entries
     */
    public function addAliases(mixed $videoId, array $aliases = []): void
    {
        $videoId = (int) $videoId;
        if ($videoId <= 0 || $aliases === []) {
            return;
        }

        $titles = [];
        foreach ($aliases as $alias) {
            if (\is_array($alias)) {
                $alias = $alias['name'] ?? '';
            }
            if (! \is_string($alias)) {
                continue;
            }
            $alias = mb_substr(trim($alias), 0, 180);
            if ($alias !== '') {
                $titles[mb_strtolower($alias)] ??= $alias;
            }
        }

        if ($titles === []) {
            return;
        }

        $now = now();
        VideoAlias::query()->insertOrIgnore(array_map(
            static fn (string $alias): array => ['videos_id' => $videoId, 'title' => $alias, 'created_at' => $now, 'updated_at' => $now],
            array_values($titles),
        ));

        Cache::forget(md5((string) $videoId));
    }

    /**
     * Retrieves all aliases for a video, the aliases matching a title, or both.
     *
     * @return Collection<int, VideoAlias>|false
     */
    public function getAliases(int $videoId, string $alias = ''): Collection|false
    {
        if ($videoId <= 0 && $alias === '') {
            return false;
        }

        /** @var Collection<int, VideoAlias> $aliases */
        $aliases = Cache::remember(
            md5($videoId.$alias),
            now()->addMinutes((int) config('nntmux.cache_expiry_medium')),
            static fn (): Collection => VideoAlias::query()
                ->when($videoId > 0, fn (Builder $query) => $query->where('videos_id', $videoId))
                ->when($alias !== '', fn (Builder $query) => $query->where('title', $alias))
                ->get(),
        );

        return $aliases->isEmpty() ? false : $aliases;
    }
}
