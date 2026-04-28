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
 * API v2 representation of a single release's full detail payload.
 *
 * Replaces the legacy `App\Transformers\DetailsTransformer`.
 */
#[TypeScript]
final class DetailsData extends Data
{
    public function __construct(
        public string $title,
        public string $details,
        public string $link,
        public int $category,
        public ?string $category_name,
        public string $added,
        public int|string|null $size,
        public int|string|null $files,
        public int|string|null $grabs,
        public int|string|null $comments,
        public int|string|null $password,
        public string $usenetdate,
        public Optional|int|string|null $imdbid = new Optional,
        public Optional|int|string|null $tmdbid = new Optional,
        public Optional|int|string|null $traktid = new Optional,
        public Optional|string|null $tvairdate = new Optional,
        public Optional|int|string|null $tvdbid = new Optional,
        public Optional|int|string|null $tvrageid = new Optional,
        public Optional|int|string|null $tvmazeid = new Optional,
    ) {}

    public static function fromRelease(Release $release, User $user): self
    {
        $categoriesId = (int) $release->categories_id;
        $base = [
            'title' => (string) $release->searchname,
            'details' => url('/').'/details/'.$release->guid,
            'link' => url('/').'/getnzb?id='.$release->guid.'.nzb&r='.$user->api_token,
            'category' => $categoriesId,
            'category_name' => $release->category_name ?? null,
            'added' => Carbon::parse($release->adddate)->toRssString(),
            'size' => $release->size,
            'files' => $release->totalpart,
            'grabs' => $release->grabs,
            'comments' => $release->comments,
            'password' => $release->passwordstatus,
            'usenetdate' => Carbon::parse($release->postdate)->toRssString(),
        ];

        if (in_array($categoriesId, Category::MOVIES_GROUP, true)) {
            return new self(
                ...$base,
                imdbid: $release->imdbid,
            );
        }

        if (in_array($categoriesId, Category::TV_GROUP, true)) {
            return new self(
                ...$base,
                imdbid: $release->imdb, // @phpstan-ignore property.notFound
                tmdbid: $release->tmdb,
                traktid: $release->trakt,
                tvairdate: $release->firstaired,
                tvdbid: $release->tvdb,
                tvrageid: $release->tvrage,
                tvmazeid: $release->tvmaze,
            );
        }

        return new self(...$base);
    }
}
