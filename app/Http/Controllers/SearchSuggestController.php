<?php

namespace App\Http\Controllers;

use Blacklight\ElasticSearchSiteSearch;
use Blacklight\ManticoreSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SearchSuggestController extends Controller
{
    private ManticoreSearch|ElasticSearchSiteSearch $searchEngine;

    public function __construct()
    {
        // Use Elasticsearch if enabled, otherwise fall back to ManticoreSearch
        if (config('nntmux.elasticsearch_enabled', false)) {
            $this->searchEngine = new ElasticSearchSiteSearch;
        } else {
            $this->searchEngine = new ManticoreSearch;
        }
    }

    /**
     * Get autocomplete suggestions for search input.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        // Use the index from search engine config, or allow override via request
        $index = $request->input('index') ?? $this->searchEngine->getReleasesIndex();

        // Rate limiting: 60 requests per minute per IP
        $key = 'autocomplete:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json([
                'success' => false,
                'error' => 'Too many requests',
            ], 429);
        }
        RateLimiter::hit($key, 60);

        if (! $this->searchEngine->isAutocompleteEnabled()) {
            return response()->json([
                'success' => true,
                'suggestions' => [],
            ]);
        }

        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'suggestions' => [],
            ]);
        }

        try {
            $suggestions = $this->searchEngine->autocomplete($query, $index);

            return response()->json([
                'success' => true,
                'query' => $query,
                'suggestions' => array_map(fn ($s) => $s['suggest'], $suggestions),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => true,
                'query' => $query,
                'suggestions' => [],
            ]);
        }
    }

    /**
     * Get spell correction suggestions ("Did you mean?").
     */
    public function suggest(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $index = $request->input('index') ?? $this->searchEngine->getReleasesIndex();

        // Rate limiting: 30 requests per minute per IP
        $key = 'suggest:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return response()->json([
                'success' => false,
                'error' => 'Too many requests',
            ], 429);
        }
        RateLimiter::hit($key, 60);

        if (! $this->searchEngine->isSuggestEnabled()) {
            return response()->json([
                'success' => true,
                'suggestions' => [],
            ]);
        }

        if (empty($query)) {
            return response()->json([
                'success' => true,
                'suggestions' => [],
            ]);
        }

        try {
            $suggestions = $this->searchEngine->suggest($query, $index);

            // Get the best suggestion (highest doc count)
            $bestSuggestion = null;
            if (! empty($suggestions)) {
                usort($suggestions, fn ($a, $b) => $b['docs'] - $a['docs']);
                $bestSuggestion = $suggestions[0]['suggest'] ?? null;
            }

            return response()->json([
                'success' => true,
                'query' => $query,
                'suggestions' => array_map(fn ($s) => $s['suggest'], $suggestions),
                'best' => $bestSuggestion,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => true,
                'query' => $query,
                'suggestions' => [],
            ]);
        }
    }

    /**
     * Combined endpoint for search assistance (autocomplete + suggest).
     */
    public function searchAssist(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $index = $request->input('index') ?? $this->searchEngine->getReleasesIndex();

        // Rate limiting
        $key = 'searchassist:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json([
                'success' => false,
                'error' => 'Too many requests',
            ], 429);
        }
        RateLimiter::hit($key, 60);

        $response = [
            'success' => true,
            'query' => $query,
            'autocomplete' => [],
            'suggest' => null,
        ];

        try {
            // Get autocomplete suggestions
            if ($this->searchEngine->isAutocompleteEnabled() && strlen($query) >= 2) {
                $autocomplete = $this->searchEngine->autocomplete($query, $index);
                $response['autocomplete'] = array_map(fn ($s) => $s['suggest'], $autocomplete);
            }

            // Get spell suggestion if query is complete (user stopped typing)
            if ($this->searchEngine->isSuggestEnabled() && ! empty($query)) {
                $suggestions = $this->searchEngine->suggest($query, $index);
                if (! empty($suggestions)) {
                    usort($suggestions, fn ($a, $b) => $b['docs'] - $a['docs']);
                    $response['suggest'] = $suggestions[0]['suggest'] ?? null;
                }
            }
        } catch (\Throwable $e) {
            // Return empty results on error
        }

        return response()->json($response);
    }
}

