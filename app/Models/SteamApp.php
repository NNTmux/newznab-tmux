<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class SteamApp extends Model
{
    use Searchable;
    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @return string
     */
    public function searchableAs()
    {
        return 'ix_name_ft';
    }

    /**
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'name' => $this->name,
        ];
    }
}
