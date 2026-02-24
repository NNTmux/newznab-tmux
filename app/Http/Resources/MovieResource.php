<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Movie API Resource for transforming movie data.
 *
 * @mixin \App\Models\MovieInfo
 */
class MovieResource extends JsonResource
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
            'imdbid' => $this->imdbid,
            'tmdbid' => $this->tmdbid,
            'traktid' => $this->traktid,
            'title' => $this->title,
            'year' => $this->year,
            'rating' => $this->rating,
            'rtrating' => $this->rtrating,
            'tagline' => $this->tagline,
            'plot' => $this->plot,
            'genre' => $this->genre,
            'type' => $this->type,
            'director' => $this->director,
            'actors' => $this->actors,
            'language' => $this->language,
            'cover' => $this->cover ? true : false,
            'backdrop' => $this->backdrop ? true : false,
            'trailer' => $this->trailer,
            'cover_url' => $this->getCoverUrl(),
            'backdrop_url' => $this->getBackdropUrl(),
            'imdb_url' => $this->imdbid ? 'https://www.imdb.com/title/tt'.$this->imdbid.'/' : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get the cover image URL using the original imdbid as stored in the database.
     */
    protected function getCoverUrl(): ?string
    {
        if (! $this->cover || ! $this->imdbid) {
            return null;
        }

        return url('/covers/movies/'.$this->imdbid.'-cover.jpg');
    }

    /**
     * Get the backdrop image URL using the original imdbid as stored in the database.
     */
    protected function getBackdropUrl(): ?string
    {
        if (! $this->backdrop || ! $this->imdbid) {
            return null;
        }

        return url('/covers/movies/'.$this->imdbid.'-backdrop.jpg');
    }
}
