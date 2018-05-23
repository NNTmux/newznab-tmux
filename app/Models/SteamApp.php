<?php

namespace App\Models;

use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\SteamApp.
 *
 * @property string $name Steam application name
 * @property int $appid Steam application id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SteamApp whereAppid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SteamApp whereName($value)
 * @mixin \Eloquent
 */
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
