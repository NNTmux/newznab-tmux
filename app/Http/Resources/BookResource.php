<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Book API Resource for transforming book data.
 *
 * @mixin \App\Models\BookInfo
 */
class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'asin' => $this->asin,
            'isbn' => $this->isbn,
            'ean' => $this->ean,
            'url' => $this->url,
            'salesrank' => $this->salesrank,
            'publisher' => $this->publisher,
            'publishdate' => $this->publishdate,
            'pages' => $this->pages,
            'overview' => $this->overview,
            'genre' => $this->genre,
            'cover' => $this->cover ? true : false,
            'cover_url' => $this->getCoverUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get the cover image URL.
     */
    protected function getCoverUrl(): ?string
    {
        if (! $this->cover) {
            return null;
        }

        $coverPath = storage_path('covers/book/'.$this->id.'.jpg');
        if (file_exists($coverPath)) {
            return url('/covers/book/'.$this->id.'.jpg');
        }

        return null;
    }
}
