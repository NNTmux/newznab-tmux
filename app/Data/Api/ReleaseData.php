<?php

declare(strict_types=1);

namespace App\Data\Api;

use App\Models\Category;
use App\Models\Release;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * API v2 representation of a release search result.
 *
 * Replaces the legacy `App\Transformers\ApiTransformer`.
 *
 * Movie/TV-only fields are typed as Optional so they are omitted from the
 * serialised output for releases of other categories — matching the previous
 * Fractal `null()` primitive behaviour.
 */
#[TypeScript]
final class ReleaseData extends Data
{
    public function __construct(
        public string $title,
        public string $details,
        public string $url,
        public int $category,
        public ?string $category_name,
        public string $added,
        public int|string|null $size,
        public int|string|null $files,
        public int|string|null $grabs,
        public int|string|null $comments,
        public int|string|null $password,
        public string $usenetdate,
        // Movie/TV optional fields
        public Optional|int|string|null $imdbid = new Optional,
        public Optional|int|string|null $tmdbid = new Optional,
        public Optional|int|string|null $traktid = new Optional,
        // TV-only
        public Optional|string|null $episode_title = new Optional,
        public Optional|string|null $season = new Optional,
        public Optional|string|null $episode = new Optional,
        public Optional|string|null $tvairdate = new Optional,
        public Optional|int|string|null $tvdbid = new Optional,
        public Optional|int|string|null $tvrageid = new Optional,
        public Optional|int|string|null $tvmazeid = new Optional,
    ) {}

    /**
     * Build a ReleaseData from an Eloquent {@see Release} or stdClass row.
     */
    public static function fromRelease(Release|\stdClass $release, User $user): self
    {
        $get = static fn (string $key, mixed $default = null): mixed => $release->{$key} ?? $default;

        $categoriesId = (int) $get('categories_id', 0);
        $guid = (string) $get('guid', '');

        $base = [
            'title' => (string) $get('searchname', ''),
            'details' => url('/details/'.$guid),
            'url' => url('/getnzb').'?id='.$guid.'.nzb&r='.$user->api_token,
            'category' => $categoriesId,
            'category_name' => $get('category_name'),
            'added' => Carbon::parse($get('adddate'))->toRssString(),
            'size' => $get('size'),
            'files' => $get('totalpart'),
            'grabs' => self::nullIfZero($get('grabs')),
            'comments' => self::nullIfZero($get('comments')),
            'password' => $get('passwordstatus'),
            'usenetdate' => Carbon::parse($get('postdate'))->toRssString(),
        ];

        if (in_array($categoriesId, Category::MOVIES_GROUP, true)) {
            return new self(
                ...$base,
                imdbid: self::nullIfZero($get('imdbid')),
                tmdbid: self::nullIfZero($get('tmdbid')),
                traktid: self::nullIfZero($get('traktid')),
            );
        }

        if (in_array($categoriesId, Category::TV_GROUP, true)) {
            return new self(
                ...$base,
                imdbid: self::nullIfZero($get('imdb')),
                tmdbid: self::nullIfZero($get('tmdb')),
                traktid: self::nullIfZero($get('trakt')),
                episode_title: $get('title'),
                season: $get('series'),
                episode: $get('episode'),
                tvairdate: $get('firstaired'),
                tvdbid: self::nullIfZero($get('tvdb')),
                tvrageid: self::nullIfZero($get('tvrage')),
                tvmazeid: self::nullIfZero($get('tvmaze')),
            );
        }

        return new self(...$base);
    }

    private static function nullIfZero(mixed $value): mixed
    {
        return ($value !== null && $value !== 0 && $value !== '' && $value !== '0') ? $value : null;
    }
}
