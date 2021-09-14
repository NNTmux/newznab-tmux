<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\XxxInfo.
 *
 * @property int $id
 * @property string $title
 * @property string $tagline
 * @property mixed|null $plot
 * @property string $genre
 * @property string|null $director
 * @property string $actors
 * @property string|null $extras
 * @property string|null $productinfo
 * @property string|null $trailers
 * @property string $directurl
 * @property string $classused
 * @property bool $cover
 * @property bool $backdrop
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereActors($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereBackdrop($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereClassused($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereCover($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereDirector($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereDirecturl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereExtras($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereGenre($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo wherePlot($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereProductinfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereTagline($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereTrailers($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo whereUpdatedAt($value)
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\XxxInfo query()
 */
class XxxInfo extends Model
{
    /**
     * @var string
     */
    protected $table = 'xxxinfo';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];
}
