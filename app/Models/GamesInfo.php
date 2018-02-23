<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class GamesInfo extends Model
{
    use Searchable;

    /**
     * @var string
     */
    protected $table = 'gamesinfo';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @return string
     */
    public function searchableAs()
    {
        return 'ix_title_ft';
    }

    /**
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'title' => $this->title,
        ];
    }
}
