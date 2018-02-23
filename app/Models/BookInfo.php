<?php

namespace App\Models;

use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;

class BookInfo extends Model
{
    use Searchable;
    /**
     * @var string
     */
    protected $table = 'bookinfo';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @return string
     */
    public function searchableAs()
    {
        return 'ix_bookinfo_author_title_ft';
    }

    /**
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'author'=> $this->author,
            'title' => $this->title,
        ];
    }
}
