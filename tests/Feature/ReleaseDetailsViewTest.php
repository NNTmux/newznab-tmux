<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MovieInfo;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReleaseDetailsViewTest extends TestCase
{
    private string $databasePath;

    public function createApplication(): Application
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'release-details-');
        new PDO('sqlite:'.$this->databasePath);

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $this->databasePath,
                'cache.default' => 'array',
                'session.driver' => 'array',
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    private function release(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 7,
            'guid' => str_repeat('a', 32),
            'searchname' => 'Pixels.2015.1080p.BluRay.x264-SPARKS',
            'category_name' => 'Movies > HD',
            'size' => 45 * 1024 * 1024,
            'totalpart' => 12,
            'grabs' => 3,
            'postdate' => now()->subDays(3)->toDateTimeString(),
            'adddate' => now()->subDays(2)->toDateTimeString(),
            'group_name' => 'alt.binaries.movies',
            'fromname' => 'poster@example.invalid',
            'imdbid' => null,
        ], $overrides);
    }

    #[Test]
    public function the_sidebar_links_category_and_group_and_uses_readable_sizes(): void
    {
        $html = view('details.partials.info-sidebar', ['release' => $this->release(), 'site' => (object) ['dereferrerLink' => '']])->render();

        $this->assertStringContainsString('href="'.url('/browse/Movies/HD').'"', $html);
        $this->assertStringContainsString('href="'.route('group', ['g' => 'alt.binaries.movies']).'"', $html);
        $this->assertStringContainsString('>45 MB</dd>', $html);
        $this->assertStringContainsString('3 days ago', $html);
        $this->assertStringNotContainsString('0.04 GB', $html);

        $movie = view('details.partials.info-sidebar', ['release' => $this->release(['imdbid' => '26657236']), 'site' => (object) ['dereferrerLink' => '']])->render();
        $this->assertStringContainsString('href="https://www.imdb.com/title/tt26657236"', $movie);
        $this->assertMatchesRegularExpression('/>\s*View on IMDb <i/', $movie);
        $this->assertDoesNotMatchRegularExpression('/>\s*tt26657236/', $movie, 'The IMDb id is not shown as link text');
    }

    #[Test]
    public function similar_releases_are_listed_with_download_buttons(): void
    {
        $similars = [];
        foreach (range(1, 10) as $index) {
            $similars[] = $this->release(['guid' => str_pad((string) $index, 32, 'b'), 'searchname' => "Pixels.2015.Release.{$index}"]);
        }

        $html = view('details.partials.similar-releases', ['similars' => $similars, 'searchname' => 'Pixels 2015'])->render();

        $this->assertSame(8, substr_count($html, 'href="'.url('/details/')), 'At most eight similar releases are shown');
        $this->assertStringContainsString('See all 10', $html);
        $this->assertStringContainsString('aria-label="Download Pixels.2015.Release.1"', $html);
        $this->assertSame('', trim(view('details.partials.similar-releases', ['similars' => []])->render()));
    }

    #[Test]
    public function releases_without_artwork_skip_the_placeholder_cover(): void
    {
        $html = view('details.partials.cover-actions', ['release' => $this->release(), 'totalReportCount' => 0, 'failed' => 0])->render();

        $this->assertStringNotContainsString('detail-cover-image', $html);
        $this->assertStringContainsString('Add to basket', $html);
        $this->assertStringNotContainsString('<h2', $html, 'The release name is the page heading, not repeated here');

        $page = (string) file_get_contents(resource_path('views/details/index.blade.php'));
        $this->assertStringContainsString(':title="$release->searchname"', $page);
        $this->assertStringContainsString("@include('details.partials.similar-releases')", $page);
    }

    #[Test]
    public function media_information_is_hidden_when_the_release_has_none(): void
    {
        $emptySubtitles = (object) ['subs' => null];
        $blankVideo = ['containerformat' => '', 'videocodec' => null, 'videowidth' => 0];

        $none = view('details.partials.media-metadata', ['reVideo' => $blankVideo, 'reAudio' => [], 'reSubs' => $emptySubtitles])->render();
        $this->assertStringNotContainsString('Media Information', $none);

        $subtitlesOnly = view('details.partials.media-metadata', ['reVideo' => false, 'reAudio' => [['audioformat' => '']], 'reSubs' => (object) ['subs' => 'English, German']])->render();
        $this->assertStringContainsString('Media Information', $subtitlesOnly);
        $this->assertStringContainsString('<span class="movie-meta-chip text-xs">German</span>', $subtitlesOnly);
        $this->assertStringNotContainsString('Video Details', $subtitlesOnly);
        $this->assertStringNotContainsString('Audio Details', $subtitlesOnly);

        $audio = view('details.partials.media-metadata', ['reVideo' => $blankVideo, 'reAudio' => [['audioformat' => 'AAC', 'audiochannels' => 2]], 'reSubs' => $emptySubtitles])->render();
        $this->assertStringContainsString('Audio Details', $audio);
        $this->assertStringNotContainsString('Video Details', $audio);
        $this->assertStringNotContainsString('Subtitles', $audio);
    }

    #[Test]
    public function media_details_are_summarised_on_single_lines(): void
    {
        $html = view('details.partials.media-metadata', [
            'reVideo' => ['containerformat' => 'Matroska', 'videoformat' => 'HEVC', 'videowidth' => 1920, 'videoheight' => 1080, 'videoframerate' => '23.976'],
            'reAudio' => [
                ['audioformat' => 'E-AC-3', 'audiochannels' => 6, 'audiobitrate' => '640 kb/s', 'audiolanguage' => 'English', 'audiosamplerate' => '48.0 kHz'],
                ['audioformat' => 'AAC', 'audiolanguage' => 'Hindi', 'audiosamplerate' => '48000'],
            ],
            'reSubs' => (object) ['subs' => implode(', ', ['English', 'English', 'Arabic', 'Czech', 'Danish', 'German', 'Greek', 'Spanish', 'Finnish', 'French', 'Hebrew', 'Hindi', 'Croatian', 'Italian'])],
        ])->render();

        $this->assertStringContainsString('Matroska · HEVC · 1920×1080 · 23.976 fps', $html);
        $this->assertStringContainsString('<li>English · E-AC-3 · 6 ch · 640 kb/s · 48.0 kHz</li>', $html);
        $this->assertStringContainsString('<li>Hindi · AAC · 48000 Hz</li>', $html);
        $this->assertStringNotContainsString('kHz Hz', $html);
        $this->assertSame(1, substr_count($html, '>English</span>'), 'Duplicate subtitle languages are listed once');
        $this->assertStringContainsString('(13)', $html);
        $this->assertStringContainsString('+1 more', $html);
    }

    #[Test]
    public function metadata_panels_share_a_two_column_grid_and_trailers_open_in_a_new_tab(): void
    {
        $page = (string) file_get_contents(resource_path('views/details/index.blade.php'));
        $this->assertStringContainsString('<div class="detail-info-grid">', $page);
        $this->assertStringNotContainsString("@include('details.partials.preview-images')", $page, 'Thumbnails live next to the cover');
        $this->assertStringContainsString("@include('details.partials.preview-images')", (string) file_get_contents(resource_path('views/details/partials/cover-actions.blade.php')));

        $movie = (new MovieInfo)->forceFill(['title' => 'Pixels', 'year' => '2015', 'genre' => 'Action', 'trailer_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
        $html = view('details.partials.movie-info', ['movie' => $movie, 'site' => (object) ['dereferrerLink' => '']])->render();
        $this->assertMatchesRegularExpression('#<a href="https://www\.youtube\.com/watch\?v=dQw4w9WgXcQ" target="_blank" rel="noopener noreferrer"[^>]*>.*Watch trailer\s*</a>#s', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertMatchesRegularExpression('/<dt[^>]*>Genre<\/dt>\s*<dd[^>]*>Action<\/dd>/', $html);
    }
}
