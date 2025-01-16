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
                'poster' => $releases->fromname,
                'imdbid' => $releases->imdbid !== null && $releases->imdbid !== 0 ? $releases->imdbid : $this->null(),
                'tmdbid' => $releases->tmdbid !== null && $releases->tmdbid !== 0 ? $releases->tmdbid : $this->null(),
                'traktid' => $releases->traktid !== null && $releases->traktid !== 0 ? $releases->traktid : $this->null(),
                'grabs' => $releases->grabs !== 0 ? $releases->grabs : $this->null(),
                'comments' => $releases->comments !== 0 ? $releases->comments : $this->null(),
                'password' => $releases->passwordstatus,
                'usenetdate' => Carbon::parse($releases->postdate)->toRssString(),
                'group' => $releases->group->name,
            ];
        }

        if (\in_array($releases->categories_id, Category::TV_GROUP, false)) {
            return [
                'title' => $releases->searchname,
                'details' => url('/').'/details/'.$releases->guid,
                'url' => url('/').'/getnzb?id='.$releases->guid.'.nzb'.'&r='.$this->user->api_token,
                'category' => $releases->categories_id,
                'category_name' => $releases->category->parent->title. ' > '. $releases->category->title,
                'added' => Carbon::parse($releases->adddate)->toRssString(),
                'size' => $releases->size,
                'files' => $releases->totalpart,
                'poster' => $releases->fromname,
                'episode_title' => $releases->episode->title ?? $this->null(),
                'season' => $releases->episode->series ?? $this->null(),
                'episode' => $releases->episode->episode ?? $this->null(),
                'tvairdate' => $releases->episode->firstaired ?? $this->null(),
                'tvdbid' => $releases->video->tvdb !== null && $releases->video->tvdb !== 0 ? $releases->video->tvdb : $this->null(),
                'traktid' => $releases->video->trakt !== null && $releases->video->trakt !== 0 ? $releases->video->trakt : $this->null(),
                'tvrageid' => $releases->video->tvrage !== null && $releases->video->tvrage !== 0 ? $releases->video->tvrage : $this->null(),
                'tvmazeid' => $releases->video->tvmaze !== null && $releases->video->tvmaze !== 0 ? $releases->video->tvmaze : $this->null(),
                'imdbid' => $releases->video->imdb !== null && $releases->video->imdb !== 0 ? $releases->video->imdb : $this->null(),
                'tmdbid' => $releases->video->tmdb !== null && $releases->video->tmdb !== 0 ? $releases->video->tmdb : $this->null(),
                'grabs' => $releases->grabs !== 0 ? $releases->grabs : $this->null(),
                'comments' => $releases->comments !== 0 ? $releases->comments : $this->null(),
                'password' => $releases->passwordstatus,
                'usenetdate' => Carbon::parse($releases->postdate)->toRssString(),
                'group' => $releases->group->name,
            ];
        }

        return [
            'title' => $releases->searchname,
            'details' => url('/').'/details/'.$releases->guid,
            'url' => url('/').'/getnzb?id='.$releases->guid.'.nzb'.'&r='.$this->user->api_token,
            'category' => $releases->categories_id,
            'category_name' => $releases->category->parent->title. ' > '. $releases->category->title,
            'added' => Carbon::parse($releases->adddate)->toRssString(),
            'size' => $releases->size,
            'files' => $releases->totalpart,
            'poster' => $releases->fromname,
            'grabs' => $releases->grabs !== 0 ? $releases->grabs : $this->null(),
            'comments' => $releases->comments !== 0 ? $releases->comments : $this->null(),
            'password' => $releases->passwordstatus,
            'usenetdate' => Carbon::parse($releases->postdate)->toRssString(),
            'group' => $releases->group->name,
        ];
    }
}
