<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\SteamApp.
 *
 * @property int $id
 * @property string $name Steam application name
 * @property int $appid Steam application id
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SteamApp whereAppid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SteamApp whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SteamApp whereName($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SteamApp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SteamApp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SteamApp query()
 */
class SteamApp extends Model
{
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];
}
