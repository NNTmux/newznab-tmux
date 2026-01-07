<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Movie Collection Resource for API responses.
 */
class MovieCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = MovieResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
            ],
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
        ];
    }

    /**
     * Get total items count when available.
     */
    protected function total(): int
    {
        if (method_exists($this->resource, 'total')) {
            return $this->resource->total();
        }

        return $this->collection->count();
    }

    /**
     * Get items per page when available.
     */
    protected function perPage(): int
    {
        if (method_exists($this->resource, 'perPage')) {
            return $this->resource->perPage();
        }

        return $this->collection->count();
    }

    /**
     * Get current page when available.
     */
    protected function currentPage(): int
    {
        if (method_exists($this->resource, 'currentPage')) {
            return $this->resource->currentPage();
        }

        return 1;
    }

    /**
     * Get last page when available.
     */
    protected function lastPage(): int
    {
        if (method_exists($this->resource, 'lastPage')) {
            return $this->resource->lastPage();
        }

        return 1;
    }

    /**
     * Get URL for a specific page.
     */
    protected function url(int $page): ?string
    {
        if (method_exists($this->resource, 'url')) {
            return $this->resource->url($page);
        }

        return null;
    }

    /**
     * Get previous page URL.
     */
    protected function previousPageUrl(): ?string
    {
        if (method_exists($this->resource, 'previousPageUrl')) {
            return $this->resource->previousPageUrl();
        }

        return null;
    }

    /**
     * Get next page URL.
     */
    protected function nextPageUrl(): ?string
    {
        if (method_exists($this->resource, 'nextPageUrl')) {
            return $this->resource->nextPageUrl();
        }

        return null;
    }
}
