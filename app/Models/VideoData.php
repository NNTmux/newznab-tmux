<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoData extends Model
{
	/**
	 * @var string
	 */
	protected $table = 'video_data';

	/**
	 * @var bool
	 */
	public $timestamps = false;

	/**
	 * @var bool
	 */
	protected $dateFormat = false;

	/**
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * @var string
	 */
	protected $primaryKey = 'releases_id';

	/**
	 * @var array
	 */
	protected $fillable = [
		'releases_id',
		'containerformat',
		'overallbitrate',
		'videoduration',
		'videoformat',
		'videocodec',
		'videowidth',
		'videoheight',
		'videoaspect',
		'videoframerate',
		'videolibrary'
	];
}
