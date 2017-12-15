<?php

namespace App\Models;

use Yadakhov\InsertOnDuplicateKey;
use Illuminate\Database\Eloquent\Model;

class TvEpisode extends Model
{
    use InsertOnDuplicateKey;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    public $timestamps = false;
}
