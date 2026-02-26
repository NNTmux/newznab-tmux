<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

/**
 * App\Models\BookInfo.
 *
 * @property int $id
 * @property string $title
 * @property string $author
 * @property string|null $asin
 * @property string|null $isbn
 * @property string|null $ean
 * @property string|null $url
 * @property int|null $salesrank
 * @property string|null $publisher
 * @property string|null $publishdate
 * @property string|null $pages
 * @property string|null $overview
 * @property string $genre
 * @property bool $cover
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereAsin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereAuthor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereCover($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereEan($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereGenre($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereIsbn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereOverview($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo wherePages($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo wherePublishdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo wherePublisher($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereSalesrank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo whereUrl($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BookInfo query()
 */
class BookInfo extends Model
{
    use Searchable;

    /**
     * @var string
     */
    protected $table = 'bookinfo';

    protected $dateFormat = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    public function searchableAs(): string
    {
        return 'ix_bookinfo_author_title_ft';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'author' => $this->author,
            'title' => $this->title,
        ];
    }

    /**
     * Get the releases associated with this book.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Release, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class, 'bookinfo_id');
    }

    /**
     * Get the cover image path.
     */
    public function getCoverPath(): string
    {
        return storage_path('covers/book/'.$this->id.'.jpg');
    }

    /**
     * Check if cover image exists.
     */
    public function hasCoverImage(): bool
    {
        return file_exists($this->getCoverPath());
    }

    /**
     * Get the cover URL.
     */
    public function getCoverUrl(): ?string
    {
        if (! $this->cover || ! $this->hasCoverImage()) {
            return null;
        }

        return url('/covers/book/'.$this->id.'.jpg');
    }
}
