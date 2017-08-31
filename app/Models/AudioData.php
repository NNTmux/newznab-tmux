<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    protected $fillable = [
		'id',
		'releases_id',
		'audioid',
		'audioformat',
		'audiomode',
		'audiobitratemode',
		'audiobitrate',
		'audiochannels',
		'audiosamplerate',
		'audiolibrary',
		'audiolanguage',
		'audiotitle',
	];
}
