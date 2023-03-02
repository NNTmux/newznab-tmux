<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\AudioData.
 *
 * @property int $id
 * @property int $releases_id FK to releases.id
 * @property int $audioid
 * @property string|null $audioformat
 * @property string|null $audiomode
 * @property string|null $audiobitratemode
 * @property string|null $audiobitrate
 * @property string|null $audiochannels
 * @property string|null $audiosamplerate
 * @property string|null $audiolibrary
 * @property string|null $audiolanguage
 * @property string|null $audiotitle
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereAudiobitrate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereAudiobitratemode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereAudiochannels($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereAudioformat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereAudioid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereAudiolanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereAudiolibrary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereAudiomode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereAudiosamplerate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereAudiotitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData whereReleasesId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\AudioData query()
 */
class AudioData extends Model
{
    /**
     * @var string
     */
    protected $table = 'audio_data';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];
}
