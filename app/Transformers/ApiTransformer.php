<?php

namespace App\Transformers;

use App\Models\Category;
use App\Models\Release;
use Illuminate\Support\Carbon;
use League\Fractal\TransformerAbstract;

class ApiTransformer extends TransformerAbstract
{
    protected $user;

    /**
     * ApiTransformer constructor.
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    public function transform(Release $releases): array
    {
        if (\in_array($releases->categories_id, Category::MOVIES_GROUP, false)) {
            return [
                'title' => $releases->searchname,
                'details' => url('/').'/details/'.$releases->guid,
                'url' => url('/').'/getnzb?id='.$releases->guid.'.nzb'.'&r='.$this->user->api_token,
                'category' => $releases->categories_id,
                'category_name' => $releases->category_name,
                'added' => Carbon::parse($releases->adddate)->toRssString(),
                'size' => $releases->size,
                'files' => $releases->totalpart,
                'imdbid' => $releases->imdbid !== null && $releases->imdbid !== 0 ? $releases->imdbid : $this->null(),
                'tmdbid' => $releases->tmdbid !== null && $releases->tmdbid !== 0 ? $releases->tmdbid : $this->null(),
                'traktid' => $releases->traktid !== null && $releases->traktid !== 0 ? $releases->traktid : $this->null(),
                'grabs' => $releases->grabs !== 0 ? $releases->grabs : $this->null(),
                'comments' => $releases->comments !== 0 ? $releases->comments : $this->null(),
                'password' => $releases->passwordstatus,
                'usenetdate' => Carbon::parse($releases->postdate)->toRssString(),
            ];
        }

        if (\in_array($releases->categories_id, Category::TV_GROUP, false)) {
            return [
                'title' => $releases->searchname,
                'details' => url('/').'/details/'.$releases->guid,
                'url' => url('/').'/getnzb?id='.$releases->guid.'.nzb'.'&r='.$this->user->api_token,
                'category' => $releases->categories_id,
                'category_name' => $releases->category_name,
                'added' => Carbon::parse($releases->adddate)->toRssString(),
                'size' => $releases->size,
                'files' => $releases->totalpart,
                'episode_title' => $releases->title ?? $this->null(),
                'season' => $releases->series ?? $this->null(),
                'episode' => $releases->episode ?? $this->null(),
                'tvairdate' => $releases->firstaired ?? $this->null(),
                'tvdbid' => $releases->tvdb !== null && $releases->tvdb !== 0 ? $releases->tvdb : $this->null(),
                'traktid' => $releases->trakt !== null && $releases->trakt !== 0 ? $releases->trakt : $this->null(),
                'tvrageid' => $releases->tvrage !== null && $releases->tvrage !== 0 ? $releases->tvrage : $this->null(),
                'tvmazeid' => $releases->tvmaze !== null && $releases->tvmaze !== 0 ? $releases->tvmaze : $this->null(),
                'imdbid' => $releases->imdb !== null && $releases->imdb !== 0 ? $releases->imdb : $this->null(),
                'tmdbid' => $releases->tmdb !== null && $releases->tmdb !== 0 ? $releases->tmdb : $this->null(),
                'grabs' => $releases->grabs !== 0 ? $releases->grabs : $this->null(),
                'comments' => $releases->comments !== 0 ? $releases->comments : $this->null(),
                'password' => $releases->passwordstatus,
                'usenetdate' => Carbon::parse($releases->postdate)->toRssString(),
            ];
        }

        return [
            'title' => $releases->searchname,
            'details' => url('/').'/details/'.$releases->guid,
            'url' => url('/').'/getnzb?id='.$releases->guid.'.nzb'.'&r='.$this->user->api_token,
            'category' => $releases->categories_id,
            'category_name' => $releases->category_name,
            'added' => Carbon::parse($releases->adddate)->toRssString(),
            'size' => $releases->size,
            'files' => $releases->totalpart,
            'grabs' => $releases->grabs !== 0 ? $releases->grabs : $this->null(),
            'comments' => $releases->comments !== 0 ? $releases->comments : $this->null(),
            'password' => $releases->passwordstatus,
            'usenetdate' => Carbon::parse($releases->postdate)->toRssString(),
        ];
    }
}
