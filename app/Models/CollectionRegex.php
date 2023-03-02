<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\CollectionRegex.
 *
 * @property int $id
 * @property string $group_regex This is a regex to match against usenet groups
 * @property string $regex Regex used for collection grouping
 * @property bool $status 1=ON 0=OFF
 * @property string $description Optional extra details on this regex
 * @property int $ordinal Order to run the regex in
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CollectionRegex whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CollectionRegex whereGroupRegex($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CollectionRegex whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CollectionRegex whereOrdinal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CollectionRegex whereRegex($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CollectionRegex whereStatus($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CollectionRegex newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CollectionRegex newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CollectionRegex query()
 */
class CollectionRegex extends Model
{
    //
}
