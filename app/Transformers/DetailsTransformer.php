<?php

namespace App\Transformers;

use App\Models\Category;
use App\Models\Release;
use Illuminate\Support\Carbon;
use League\Fractal\TransformerAbstract;

class DetailsTransformer extends TransformerAbstract
{
    protected $user;

    /**
     * DetailsTransformer constructor.
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Transform a release into a details array.
     */
    public function transform(Release $release): array
    {
        // Base data common to all releases
        $data = [
            'title' => $release->searchname,
            'details' => url('/').'/details/'.$release->guid,
            'link' => url('/').'/getnzb?id='.$release->guid.'.nzb&r='.$this->user->api_token,
            'category' => $release->categories_id,
            'category_name' => $release->category_name,
            'added' => Carbon::parse($release->adddate)->toRssString(),
            'size' => $release->size,
            'files' => $release->totalpart,
            'grabs' => $release->grabs,
            'comments' => $release->comments,
            'password' => $release->passwordstatus,
            'usenetdate' => Carbon::parse($release->postdate)->toRssString(),
        ];

        // Add movie-specific data
        if (\in_array($release->categories_id, Category::MOVIES_GROUP, false)) {
            $data['imdbid'] = $release->imdbid;
        }

        // Add TV-specific data
        if (\in_array($release->categories_id, Category::TV_GROUP, false)) {
            $data['tvairdate'] = $release->firstaired;
            $data['tvdbid'] = $release->tvdb;
            $data['traktid'] = $release->trakt;
            $data['tvrageid'] = $release->tvrage;
            $data['tvmazeid'] = $release->tvmaze;
            $data['imdbid'] = $release->imdb;
            $data['tmdbid'] = $release->tmdb;
        }

        return $data;
    }
}
