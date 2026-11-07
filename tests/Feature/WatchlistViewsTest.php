<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WatchlistViewsTest extends TestCase
{
    #[Test]
    public function the_movie_edit_form_is_full_width_with_preselected_categories(): void
    {
        $html = view('mymovies.add', [
            'type' => 'edit',
            'imdbid' => '0133093',
            'movie' => ['title' => 'The Matrix', 'year' => 1999],
            'cat_ids' => [2040, 2045],
            'cat_names' => [2040 => 'HD', 2045 => 'UHD'],
            'cat_selected' => ['2045'],
            'from' => '/mymovies',
            'userdata' => (object) ['id' => 1, 'api_token' => 'token'],
        ])->render();

        $this->assertStringContainsString('Edit movie', $html);
        $this->assertStringContainsString('The Matrix (1999)', $html);
        $this->assertStringContainsString('Save categories', $html);
        $this->assertMatchesRegularExpression('/value="2045"[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="2040"[^>]*checked/', $html);
        $this->assertStringContainsString('action="'.url('mymovies?id=doedit').'"', $html);
        $this->assertStringNotContainsString('max-w-4xl', $html);
        $this->assertStringNotContainsString('No movies bookmarked yet', $html, 'The add page no longer repeats the My movies list.');
    }

    #[Test]
    public function the_show_add_form_uses_the_shared_layout(): void
    {
        $html = view('myshows.add', [
            'type' => 'add',
            'video' => 7,
            'show' => ['title' => 'Severance'],
            'cat_ids' => [5040],
            'cat_names' => [5040 => 'HD'],
            'cat_selected' => [],
            'userdata' => (object) ['id' => 1, 'api_token' => 'token'],
        ])->render();

        $this->assertStringContainsString('Add TV show', $html);
        $this->assertStringContainsString('Add show', $html);
        $this->assertStringContainsString('name="id" value="7"', $html);
        $this->assertStringContainsString('action="'.url('myshows?action=doadd').'"', $html);
        $this->assertStringNotContainsString('max-w-4xl', $html);
    }

    #[Test]
    public function my_movies_lists_each_movie_as_a_compact_row(): void
    {
        $html = view('mymovies.index', [
            'movies' => [[
                'imdbid' => '0133093', 'title' => 'The Matrix', 'year' => 1999, 'cover' => 0,
                'genre' => 'Action', 'director' => 'Lana Wachowski', 'categoryNames' => '', 'created_at' => '2026-01-02 10:00:00',
            ]],
            'userdata' => (object) ['id' => 1, 'api_token' => 'token'],
            'site' => (object) ['dereferrerLink' => ''],
        ])->render();

        $this->assertStringContainsString('Action · Directed by Lana Wachowski', $html);
        $this->assertStringContainsString('All categories', $html);
        $this->assertStringContainsString('aria-label="Edit categories for The Matrix"', $html);
        $this->assertStringContainsString('aria-label="Remove The Matrix from My movies"', $html);
        $this->assertSame(1, substr_count($html, 'Browse movies'), 'Header actions are not repeated below the header.');
        $this->assertStringNotContainsString('max-w-7xl', $html);
    }
}
