<?php

namespace App\Transformers;

use Blacklight\Releases;
use Illuminate\Support\Carbon;
use League\Fractal\TransformerAbstract;

class MoviesTransformer extends TransformerAbstract
{
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(\stdClass $releases)
    {

        return [
            'title' => $releases->searchname,
            'details' => url('/').'/details/'.$releases->guid,
            'link' => url('/').'/getnzb?id='.$releases->guid.'.nzb',
            'category' => $releases->categories_id,
            'category name' => $releases->category_name,
            'size' => $releases->size,
            'files' => $releases->totalpart,
            'poster' => $releases->fromname,
            'imdbid' => $releases->imdbid,
            'grabs' => $releases->grabs,
            'comments' => $releases->comments,
            'password' => $releases->passwordstatus,
            'usenetdate' => Carbon::parse($releases->postdate)->format('D, d M Y H:i:s O'),
            'group' => $releases->group_name,
        ];
    }
}
