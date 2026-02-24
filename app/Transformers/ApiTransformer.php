<?php

namespace App\Transformers;

use App\Models\Category;
use App\Models\Release;
use App\Models\User;
use Illuminate\Support\Carbon;
use League\Fractal\TransformerAbstract;

class ApiTransformer extends TransformerAbstract
{
    protected User $user;

    /**
     * ApiTransformer constructor.
     *
     * @param  User  $user  The authenticated user for API access
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Transform a release into an API response array.
     *
     * @param  Release|\stdClass  $release  The release to transform (can be Eloquent model or stdClass from raw query)
     * @return array<string, mixed> The transformed release data
     */
    public function transform(Release|\stdClass $release): array
    {
        $data = $this->getBaseData($release);

        $categoriesId = $this->getValue($release, 'categories_id');

        if (\in_array($categoriesId, Category::MOVIES_GROUP, true)) {
            return array_merge($data, $this->getMovieSpecificData($release));
        }

        if (\in_array($categoriesId, Category::TV_GROUP, true)) {
            return array_merge($data, $this->getTvSpecificData($release));
        }

        return $data;
    }

    /**
     * Get a value from the release object, handling both Eloquent models and stdClass objects.
     */
    protected function getValue(Release|\stdClass $release, string $key, mixed $default = null): mixed
    {
        if ($release instanceof Release) {
            return $release->{$key} ?? $default;
        }

        return $release->{$key} ?? $default;
    }

    /**
     * Get base data common to all releases.
     *
     * @return array<string, mixed>
     */
    protected function getBaseData(Release|\stdClass $release): array
    {
        return [
            'title' => $this->getValue($release, 'searchname'),
            'details' => $this->getDetailsUrl($this->getValue($release, 'guid')),
            'url' => $this->getDownloadUrl($this->getValue($release, 'guid')),
            'category' => $this->getValue($release, 'categories_id'),
            'category_name' => $this->getValue($release, 'category_name'),
            'added' => Carbon::parse($this->getValue($release, 'adddate'))->toRssString(),
            'size' => $this->getValue($release, 'size'),
            'files' => $this->getValue($release, 'totalpart'),
            'grabs' => $this->nullIfZero($this->getValue($release, 'grabs')),
            'comments' => $this->nullIfZero($this->getValue($release, 'comments')),
            'password' => $this->getValue($release, 'passwordstatus'),
            'usenetdate' => Carbon::parse($this->getValue($release, 'postdate'))->toRssString(),
        ];
    }

    /**
     * Get movie-specific data fields.
     *
     * @return array<string, mixed>
     */
    protected function getMovieSpecificData(Release|\stdClass $release): array
    {
        return [
            'imdbid' => $this->nullIfZero($this->getValue($release, 'imdbid')),
            'tmdbid' => $this->nullIfZero($this->getValue($release, 'tmdbid')),
            'traktid' => $this->nullIfZero($this->getValue($release, 'traktid')),
        ];
    }

    /**
     * Get TV-specific data fields.
     *
     * @return array<string, mixed>
     */
    protected function getTvSpecificData(Release|\stdClass $release): array
    {
        return [
            'episode_title' => $this->getValue($release, 'title') ?? $this->null(),
            'season' => $this->getValue($release, 'series') ?? $this->null(),
            'episode' => $this->getValue($release, 'episode') ?? $this->null(),
            'tvairdate' => $this->getValue($release, 'firstaired') ?? $this->null(),
            'tvdbid' => $this->nullIfZero($this->getValue($release, 'tvdb')),
            'traktid' => $this->nullIfZero($this->getValue($release, 'trakt')),
            'tvrageid' => $this->nullIfZero($this->getValue($release, 'tvrage')),
            'tvmazeid' => $this->nullIfZero($this->getValue($release, 'tvmaze')),
            'imdbid' => $this->nullIfZero($this->getValue($release, 'imdb')),
            'tmdbid' => $this->nullIfZero($this->getValue($release, 'tmdb')),
        ];
    }

    /**
     * Generate the details URL for a release.
     */
    protected function getDetailsUrl(string $guid): string
    {
        return url('/details/'.$guid);
    }

    /**
     * Generate the download URL for a release.
     */
    protected function getDownloadUrl(string $guid): string
    {
        return url('/getnzb').'?id='.$guid.'.nzb&r='.$this->user->api_token;
    }

    /**
     * Return null if the value is zero, otherwise return the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function nullIfZero($value)
    {
        return ($value !== null && $value !== 0) ? $value : $this->null();
    }
}
