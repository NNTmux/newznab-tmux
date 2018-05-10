<?php

namespace App\Models;

use App\Support\Database\CacheQueryBuilder;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;

class MusicInfo extends Model
{
    use Searchable;
    use CacheQueryBuilder;
    /**
     * @var string
     */
    protected $table = 'musicinfo';
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var array
     */
    protected $guarded = [];

    public function genre()
    {
        return $this->belongsTo(Genre::class, 'genres_id');
    }

    /**
     * @return string
     */
    public function searchableAs()
    {
        return 'ix_musicinfo_artist_title_ft';
    }

    /**
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'artist'=> $this->artist,
            'title' => $this->title,
        ];
    }
}
