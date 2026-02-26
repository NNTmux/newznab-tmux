<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\CategoryRegex.
 *
 * @property int $id
 * @property string $group_regex This is a regex to match against usenet groups
 * @property string $regex Regex used to match a release name to categorize it
 * @property bool $status 1=ON 0=OFF
 * @property string $description Optional extra details on this regex
 * @property int $ordinal Order to run the regex in
 * @property int $categories_id Which categories id to put the release in
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CategoryRegex whereCategoriesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CategoryRegex whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CategoryRegex whereGroupRegex($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CategoryRegex whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CategoryRegex whereOrdinal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CategoryRegex whereRegex($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CategoryRegex whereStatus($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CategoryRegex newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CategoryRegex newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\CategoryRegex query()
 */
class CategoryRegex extends Model
{
    //
}
