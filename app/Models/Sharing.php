<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Sharing.
 *
 * @property int $site_guid
 * @property string $site_name
 * @property string $username
 * @property bool $enabled
 * @property bool $posting
 * @property bool $fetching
 * @property bool $auto_enable
 * @property bool $start_position
 * @property bool $hide_users
 * @property int $last_article
 * @property int $max_push
 * @property int $max_download
 * @property int $max_pull
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereAutoEnable($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereFetching($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereHideUsers($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereLastArticle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereMaxDownload($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereMaxPull($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereMaxPush($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing wherePosting($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereSiteGuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereSiteName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereStartPosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing whereUsername($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Sharing query()
 */
class Sharing extends Model
{
    /**
     * @var string
     */
    protected $table = 'sharing';

    /**
     * @var string
     */
    protected $primaryKey = 'site_guid';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;
}
