<?php

namespace App\Transformers;

use App\Models\Category;
use App\Models\Release;
use App\Models\User;
use Illuminate\Support\Carbon;
use League\Fractal\TransformerAbstract;

class ApiTransformer extends TransformerAbstract
{
    /**
     * @var User
     */
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
     * @param  Release  $release  The release to transform
     * @return array The transformed release data
     */
    public function transform(Release $release): array
    {
        $data = $this->getBaseData($release);

        if (\in_array($release->categories_id, Category::MOVIES_GROUP, false)) {
            return array_merge($data, $this->getMovieSpecificData($release));
        }

        if (\in_array($release->categories_id, Category::TV_GROUP, false)) {
            return array_merge($data, $this->getTvSpecificData($release));
        }

        return $data;
    }

    /**
     * Get base data common to all releases.
     *
     * @param  Release  $release
     * @return array
     */
    protected function getBaseData(Release $release): array
    {
        return [
            'title' => $release->searchname,
            'details' => $this->getDetailsUrl($release->guid),
            'url' => $this->getDownloadUrl($release->guid),
            'category' => $release->categories_id,
            'category_name' => $release->category_name,
            'added' => Carbon::parse($release->adddate)->toRssString(),
            'size' => $release->size,
            'files' => $release->totalpart,
            'grabs' => $this->nullIfZero($release->grabs),
            'comments' => $this->nullIfZero($release->comments),
            'password' => $release->passwordstatus,
            'usenetdate' => Carbon::parse($release->postdate)->toRssString(),
        ];
    }

    /**
     * Get movie-specific data fields.
     *
     * @param  Release  $release
     * @return array
     */
    protected function getMovieSpecificData(Release $release): array
    {
        return [
            'imdbid' => $this->nullIfZero($release->imdbid),
            'tmdbid' => $this->nullIfZero($release->tmdbid),
            'traktid' => $this->nullIfZero($release->traktid),
        ];
    }

    /**
     * Get TV-specific data fields.
     *
     * @param  Release  $release
     * @return array
     */
    protected function getTvSpecificData(Release $release): array
    {
        return [
            'episode_title' => $release->title ?? $this->null(),
            'season' => $release->series ?? $this->null(),
            'episode' => $release->episode ?? $this->null(),
            'tvairdate' => $release->firstaired ?? $this->null(),
            'tvdbid' => $this->nullIfZero($release->tvdb),
            'traktid' => $this->nullIfZero($release->trakt),
            'tvrageid' => $this->nullIfZero($release->tvrage),
            'tvmazeid' => $this->nullIfZero($release->tvmaze),
            'imdbid' => $this->nullIfZero($release->imdb),
            'tmdbid' => $this->nullIfZero($release->tmdb),
        ];
    }

    /**
     * Generate the details URL for a release.
     *
     * @param  string  $guid
     * @return string
     */
    protected function getDetailsUrl(string $guid): string
    {
        return url('/details/'.$guid);
    }

    /**
     * Generate the download URL for a release.
     *
     * @param  string  $guid
     * @return string
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
