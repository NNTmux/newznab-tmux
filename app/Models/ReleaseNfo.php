<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseNfo extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    protected $primaryKey = 'releases_id';

    /**
     * @var array
     */
    protected $guarded = [];

    public function release()
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }

    /**
     * @param $id
     * @param bool $getNfoString
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getReleaseNfo($id, $getNfoString = true)
    {
        $nfo = self::query()->where('releases_id', $id)->whereNotNull('nfo')->select(['releases_id']);
        if ($getNfoString === true) {
            $nfo->selectRaw('UNCOMPRESS(nfo) AS nfo');
        }

        return $nfo->first();
    }
}
