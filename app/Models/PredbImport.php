<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\PredbImport.
 *
 * @property string $title
 * @property string|null $nfo
 * @property string|null $size
 * @property string|null $category
 * @property string|null $predate
 * @property string $source
 * @property int $requestid
 * @property int $groups_id FK to groups
 * @property bool $nuked Is this pre nuked? 0 no 2 yes 1 un nuked 3 mod nuked
 * @property string|null $nukereason If this pre is nuked, what is the reason?
 * @property string|null $files How many files does this pre have ?
 * @property string $filename
 * @property bool $searched
 * @property string|null $groupname
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereFiles($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereGroupname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereGroupsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereNfo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereNuked($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereNukereason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport wherePredate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereRequestid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereSearched($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport whereTitle($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\PredbImport query()
 */
class PredbImport extends Model
{
    //
}
