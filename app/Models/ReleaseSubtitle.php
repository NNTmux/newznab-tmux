<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseSubtitle extends Model
{
	/**
	 * @var string
	 */
	protected $table = 'release_subtitles';

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
		'subsid',
		'subslanguage'
	];
}
