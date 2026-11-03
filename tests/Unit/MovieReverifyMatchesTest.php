<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Release;
use App\Services\MovieService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class MovieReverifyMatchesTest extends ImdbScraperTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('movieinfo');
        Schema::create('movieinfo', function (Blueprint $table): void {
            $table->id();
            $table->string('imdbid')->unique();
            $table->unsignedInteger('tmdbid')->default(0);
            $table->unsignedInteger('traktid')->default(0);
            $table->string('title')->default('');
            $table->string('tagline')->default('');
            $table->string('rating', 4)->default('');
            $table->string('rtrating', 10)->default('');
            $table->string('plot')->default('');
            $table->string('year', 4)->default('');
            $table->string('genre', 64)->default('');
            $table->string('type', 32)->default('');
            $table->string('director', 64)->default('');
            $table->text('actors')->default('');
            $table->string('language', 64)->default('');
            $table->boolean('cover')->default(false);
            $table->boolean('backdrop')->default(false);
            $table->string('trailer')->default('');
            $table->timestamps();
        });

        Schema::dropIfExists('releases');
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            $table->string('searchname')->default('');
            $table->unsignedInteger('categories_id')->default(0);
            $table->string('imdbid')->nullable();
            $table->unsignedBigInteger('movieinfo_id')->nullable();
        });
    }

    #[Test]
    public function it_resets_releases_whose_imdb_match_no_longer_verifies(): void
    {
        Cache::flush();

        $movieService = new MovieService;
        $movieService->update([
            'imdbid' => '13622970',
            'title' => 'Moana 2',
            'year' => '2024',
        ]);
        $movieService->update([
            'imdbid' => '39369643',
            'title' => 'State of Fear',
            'year' => '2026',
        ]);
        $movieService->update([
            'imdbid' => '10703978',
            'title' => 'The Free Fall',
            'year' => '2021',
        ]);

        Release::query()->insert([
            [
                'id' => 11,
                'searchname' => 'Moana.2026.1080p.AMZN.WEB-DL.H.264-HDShare',
                'categories_id' => 2000,
                'imdbid' => '13622970',
                'movieinfo_id' => 1,
            ],
            [
                'id' => 12,
                'searchname' => 'Moana.2.2024.1080p.WEB-DL.x265-Group',
                'categories_id' => 2000,
                'imdbid' => '13622970',
                'movieinfo_id' => 1,
            ],
            [
                'id' => 13,
                'searchname' => 'Salve.Geral.Irmandade.2026.2160p.Netflix.WEB-DL.HEVC.10bit.DDP5.1.Atmos.6Audios-QHstudIo',
                'categories_id' => 2000,
                'imdbid' => '39369643',
                'movieinfo_id' => 2,
            ],
            [
                'id' => 14,
                'searchname' => 'The.Free.Fall.2022.1080p.AMZN.WEB-DL.DDP5.1.H.264-GPRS',
                'categories_id' => 2000,
                'imdbid' => '10703978',
                'movieinfo_id' => 3,
            ],
        ]);

        $this->artisan('movie:reverify-matches')->assertSuccessful();

        $this->assertNull(Release::query()->whereKey(11)->value('imdbid'));
        $this->assertNull(Release::query()->whereKey(11)->value('movieinfo_id'));
        $this->assertSame('13622970', Release::query()->whereKey(12)->value('imdbid'));
        // Alternate/international title with matching year must be kept.
        $this->assertSame('39369643', Release::query()->whereKey(13)->value('imdbid'));
        // Exact title with a one-year mislabel must be kept.
        $this->assertSame('10703978', Release::query()->whereKey(14)->value('imdbid'));
    }

    #[Test]
    public function it_only_reports_mismatches_in_dry_run_mode(): void
    {
        Cache::flush();

        $movieService = new MovieService;
        $movieService->update([
            'imdbid' => '13622970',
            'title' => 'Moana 2',
            'year' => '2024',
        ]);

        Release::query()->insert([
            'id' => 21,
            'searchname' => 'Moana.2026.1080p.AMZN.WEB-DL.H.264-HDShare',
            'categories_id' => 2000,
            'imdbid' => '13622970',
            'movieinfo_id' => 1,
        ]);

        $this->artisan('movie:reverify-matches', ['--dry-run' => true])
            ->expectsOutputToContain('1 mismatches')
            ->assertSuccessful();

        $this->assertSame('13622970', Release::query()->whereKey(21)->value('imdbid'));
    }

    #[Test]
    public function it_requeues_stale_links_and_links_releases_to_existing_movieinfo(): void
    {
        $this->seedRepairLinkRows();

        $this->artisan('movie:reverify-matches', ['--repair-links' => true])
            ->expectsTable(['Repair', 'Changed'], [
                ['Stale movie links requeued for lookup', '1'],
                ['Releases linked to existing movieinfo', '2'],
            ])
            ->assertSuccessful();

        // Failed lookup next to an old movie link: both cleared so the worker looks it up again.
        $this->assertNull(Release::query()->whereKey(31)->value('imdbid'));
        $this->assertNull(Release::query()->whereKey(31)->value('movieinfo_id'));
        // Known IMDb id without a link (NULL or 0) gets the matching movieinfo row.
        $this->assertSame(2, (int) Release::query()->whereKey(32)->value('movieinfo_id'));
        $this->assertSame(2, (int) Release::query()->whereKey(33)->value('movieinfo_id'));
        // Unknown IMDb id, a correct link, a non-movie release and a pending id stay as they were.
        $this->assertNull(Release::query()->whereKey(34)->value('movieinfo_id'));
        $this->assertSame(1, (int) Release::query()->whereKey(35)->value('movieinfo_id'));
        $this->assertSame('', Release::query()->whereKey(36)->value('imdbid'));
        $this->assertSame(1, (int) Release::query()->whereKey(36)->value('movieinfo_id'));
        $this->assertNull(Release::query()->whereKey(37)->value('movieinfo_id'));
    }

    #[Test]
    public function it_only_counts_link_repairs_in_dry_run_mode(): void
    {
        $this->seedRepairLinkRows();

        $this->artisan('movie:reverify-matches', ['--repair-links' => true, '--dry-run' => true])
            ->expectsTable(['Repair', 'Would change'], [
                ['Stale movie links requeued for lookup', '1'],
                ['Releases linked to existing movieinfo', '2'],
            ])
            ->assertSuccessful();

        $this->assertSame('', Release::query()->whereKey(31)->value('imdbid'));
        $this->assertSame(1, (int) Release::query()->whereKey(31)->value('movieinfo_id'));
        $this->assertNull(Release::query()->whereKey(32)->value('movieinfo_id'));
    }

    private function seedRepairLinkRows(): void
    {
        Cache::flush();

        $movieService = new MovieService;
        $movieService->update(['imdbid' => '0087332', 'title' => 'Ghostbusters', 'year' => '1984']);
        $movieService->update(['imdbid' => '0097428', 'title' => 'Ghostbusters II', 'year' => '1989']);

        Release::query()->insert([
            ['id' => 31, 'searchname' => 'Ghostbusters.2.German.1989.AC3.DVDRip.XviD-CENTi', 'categories_id' => 2030, 'imdbid' => '', 'movieinfo_id' => 1],
            ['id' => 32, 'searchname' => 'Ghostbusters.II.1989.1080p.BluRay.x264-GRP', 'categories_id' => 2040, 'imdbid' => '0097428', 'movieinfo_id' => null],
            ['id' => 33, 'searchname' => 'Ghostbusters.II.1989.720p.BluRay.x264-GRP', 'categories_id' => 2040, 'imdbid' => '0097428', 'movieinfo_id' => 0],
            ['id' => 34, 'searchname' => 'Unknown.Movie.2020.1080p.WEB-DL-GRP', 'categories_id' => 2040, 'imdbid' => '1234567', 'movieinfo_id' => null],
            ['id' => 35, 'searchname' => 'Ghostbusters.1984.1080p.BluRay.x264-GRP', 'categories_id' => 2040, 'imdbid' => '0087332', 'movieinfo_id' => 1],
            ['id' => 36, 'searchname' => 'Some.Show.S01E01.1080p.WEB-DL-GRP', 'categories_id' => 5040, 'imdbid' => '', 'movieinfo_id' => 1],
            ['id' => 37, 'searchname' => 'Pending.Movie.2020.1080p.WEB-DL-GRP', 'categories_id' => 2040, 'imdbid' => '0000000', 'movieinfo_id' => null],
        ]);
    }
}
