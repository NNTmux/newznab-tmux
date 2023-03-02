<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReleaseNamingRegex.
 *
 * @property int $id
 * @property string $group_regex This is a regex to match against usenet groups
 * @property string $regex Regex used for extracting name from subject
 * @property bool $status 1=ON 0=OFF
 * @property string $description Optional extra details on this regex
 * @property int $ordinal Order to run the regex in
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNamingRegex whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNamingRegex whereGroupRegex($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNamingRegex whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNamingRegex whereOrdinal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNamingRegex whereRegex($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNamingRegex whereStatus($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNamingRegex newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNamingRegex newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseNamingRegex query()
 */
class ReleaseNamingRegex extends Model
{
    //
}
