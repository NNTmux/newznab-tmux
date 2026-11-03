<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TvLookupStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\TvProcessingSchema;
use Tests\TestCase;

class RelinkLocalTvEpisodesTest extends TestCase
{
    use TvProcessingSchema;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->createTvProcessingSchema();
    }

    #[Test]
    public function it_links_half_done_and_legacy_releases_to_episodes_stored_since(): void
    {
        $show = $this->insertVideo('Gogglebox', ['tvdb' => 268009]);
        $episode = $this->insertEpisode($show, 28, 1, '2026-09-04');
        $pending = $this->insertTvRelease('Gogglebox.S28E01.1080p.HDTV.H264-DARKFLiX', ['videos_id' => $show]);
        $legacy = $this->insertTvRelease('Gogglebox.S28E01.720p.WEB.H.264-JFF', ['videos_id' => $show, 'tv_episodes_id' => -1]);
        $missing = $this->insertTvRelease('Gogglebox.S28E01.480p.HDTV.x264-RMTeam', [
            'videos_id' => $show, 'tv_episodes_id' => TvLookupStatus::EpisodeMissing->value, 'tv_lookup_attempts' => 5, 'tv_lookup_retry_at' => now()->addDay(),
        ]);
        $notYet = $this->insertTvRelease('Gogglebox.S28E09.1080p.HDTV.H264-DARKFLiX', ['videos_id' => $show, 'tv_episodes_id' => TvLookupStatus::EpisodeMissing->value, 'tv_lookup_attempts' => 2]);
        $pack = $this->insertTvRelease('Gogglebox.S28.720p.WEB.H.264-JFF', ['videos_id' => $show]);

        $this->artisan('tv:relink-local')->assertSuccessful();

        foreach ([$pending, $legacy, $missing] as $releaseId) {
            $this->assertRelease($releaseId, $show, $episode);
        }
        $this->assertRelease($notYet, $show, TvLookupStatus::EpisodeMissing->value, 2);
        $this->assertRelease($pack, $show, TvLookupStatus::SeasonPack->value);
    }

    #[Test]
    public function it_moves_a_release_to_the_same_named_show_that_holds_its_episode(): void
    {
        $kids = $this->insertVideo('Jeopardy', ['countries_id' => 'GB', 'started' => '2002-04-26']);
        $this->insertEpisode($kids, 1, 1, '2002-04-26');
        $quiz = $this->insertVideo('Jeopardy!', ['started' => '1964-03-01']);
        $episode = $this->insertEpisode($quiz, 43, 30, '2026-10-27');
        $releaseId = $this->insertTvRelease('Jeopardy.2026.10.27.1080p.HDTV.AAC2.0.x264-MacP', ['videos_id' => $kids, 'tv_episodes_id' => TvLookupStatus::EpisodeMissing->value]);

        $this->artisan('tv:relink-local')->assertSuccessful();

        $this->assertRelease($releaseId, $quiz, $episode);
    }

    #[Test]
    public function it_resolves_unmatched_releases_from_local_shows_and_requeues_names_it_can_parse_now(): void
    {
        $hundred = $this->insertVideo('The 100');
        $episode = $this->insertEpisode($hundred, 1, 1);
        $linked = $this->insertTvRelease('The.100.S01E01.Pilot.1080p.AMZN.WEB-DL.DD+5.1.H.264-playWEB', ['tv_episodes_id' => TvLookupStatus::ShowNotFound->value]);
        $showOnly = $this->insertTvRelease('The.100.S07E16.1080p.AMZN.WEB-DL.DD+5.1.H.264-playWEB', ['tv_episodes_id' => TvLookupStatus::ShowNotFound->value]);
        $parseable = $this->insertTvRelease('"ghost.hunters.s08e05.hdtv.xvid-nmbstv.vol03+04.par2"', ['tv_episodes_id' => TvLookupStatus::ParseFailed->value]);
        $unknown = $this->insertTvRelease('Unknown.Show.S01E01.720p.WEB.h264-GRP', ['tv_episodes_id' => TvLookupStatus::ShowNotFound->value]);

        $this->artisan('tv:relink-local', ['--unmatched' => true])->assertSuccessful();

        $this->assertRelease($linked, $hundred, $episode);
        $this->assertRelease($showOnly, $hundred, TvLookupStatus::EpisodeMissing->value, 1);
        $this->assertRelease($parseable, 0, TvLookupStatus::Pending->value);
        $this->assertRelease($unknown, 0, TvLookupStatus::ShowNotFound->value);
    }

    #[Test]
    public function a_dry_run_changes_nothing(): void
    {
        $show = $this->insertVideo('Gogglebox');
        $this->insertEpisode($show, 28, 1);
        $releaseId = $this->insertTvRelease('Gogglebox.S28E01.1080p.HDTV.H264-DARKFLiX', ['videos_id' => $show, 'tv_episodes_id' => -1]);

        $this->artisan('tv:relink-local', ['--dry-run' => true])->assertSuccessful();

        $this->assertRelease($releaseId, $show, -1);
    }

    #[Test]
    public function a_limited_run_resumes_after_the_last_show_it_walked(): void
    {
        $first = $this->insertVideo('Gogglebox');
        $firstEpisode = $this->insertEpisode($first, 28, 1);
        $second = $this->insertVideo('Hollyoaks');
        $secondEpisode = $this->insertEpisode($second, 28, 100);
        $a = $this->insertTvRelease('Gogglebox.S28E01.1080p.HDTV.H264-DARKFLiX', ['videos_id' => $first]);
        $b = $this->insertTvRelease('Hollyoaks.S28E100.1080p.ALL4.WEB-DL.AAC2.0.H.264-FUZEER', ['videos_id' => $second]);

        $this->artisan('tv:relink-local', ['--limit' => 1, '--shows' => 1])->assertSuccessful();
        $this->assertRelease($a, $first, $firstEpisode);
        $this->assertRelease($b, $second, 0);

        $this->artisan('tv:relink-local', ['--limit' => 1, '--shows' => 1])->assertSuccessful();
        $this->assertRelease($b, $second, $secondEpisode);
    }

    #[Test]
    public function a_limited_unmatched_run_resumes_within_and_across_states(): void
    {
        $show = $this->insertVideo('The 100');
        $episode = $this->insertEpisode($show, 1, 1);
        $a = $this->insertTvRelease('The.100.S01E01.720p.WEB.h264-GRP', ['tv_episodes_id' => TvLookupStatus::ShowNotFound->value]);
        $b = $this->insertTvRelease('The.100.S01E01.1080p.WEB.h264-GRP', ['tv_episodes_id' => TvLookupStatus::ShowNotFound->value]);
        $c = $this->insertTvRelease('The.100.S01E01.2160p.WEB.h265-GRP', ['tv_episodes_id' => TvLookupStatus::ParseFailed->value]);

        foreach ([$a, $b, $c] as $releaseId) {
            $this->artisan('tv:relink-local', ['--unmatched' => true, '--limit' => 1, '--chunk' => 1])->assertSuccessful();
            $this->assertRelease($releaseId, $show, $episode);
        }
    }

    #[Test]
    public function reprocess_unmatched_can_requeue_recent_misses_for_the_workers(): void
    {
        $recent = $this->insertTvRelease('Some.Show.S01E01.720p.WEB.h264-GRP', ['tv_episodes_id' => TvLookupStatus::ShowNotFound->value, 'adddate' => now()->subDay()]);
        $old = $this->insertTvRelease('Old.Show.S01E01.720p.WEB.h264-GRP', ['tv_episodes_id' => TvLookupStatus::ShowNotFound->value, 'adddate' => now()->subYear()]);

        $this->artisan('tv:reprocess-unmatched', ['--requeue' => true, '--since' => 30])->assertSuccessful();

        $this->assertRelease($recent, 0, TvLookupStatus::Pending->value);
        $this->assertRelease($old, 0, TvLookupStatus::ShowNotFound->value);
    }

    private function assertRelease(int $releaseId, int $videoId, int $episodeState, int $attempts = 0): void
    {
        $release = DB::table('releases')->find($releaseId);

        $this->assertSame(
            ['videos_id' => $videoId, 'tv_episodes_id' => $episodeState, 'tv_lookup_attempts' => $attempts],
            ['videos_id' => (int) $release->videos_id, 'tv_episodes_id' => (int) $release->tv_episodes_id, 'tv_lookup_attempts' => (int) $release->tv_lookup_attempts],
            (string) $release->searchname,
        );
    }
}
