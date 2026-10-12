<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TvLookupStatus;
use App\Facades\Search;
use App\Services\TvProcessing\Providers\AbstractTvProvider;
use App\Services\TvProcessing\Providers\LocalDbProvider;
use App\Services\TvProcessing\TvLookupOutcomeWriter;
use App\Services\TvProcessing\TvProcessingResult;
use App\Services\TvProcessing\TvReleaseContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\TvProcessingSchema;
use Tests\TestCase;

class TvProviderStorageTest extends TestCase
{
    use TvProcessingSchema;

    private LocalDbProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->createTvProcessingSchema();
        $this->provider = new LocalDbProvider;
    }

    public function test_add_episode_returns_the_stored_episode_id(): void
    {
        $other = $this->insertVideo('Other Show');
        $this->insertEpisode($other, 1, 1);
        $videoId = $this->insertVideo('The Expanse');

        $first = $this->provider->addEpisode($videoId, $this->episode(1, 2, '2015-12-14'));
        $again = $this->provider->addEpisode($videoId, $this->episode(1, 2, '2015-12-14'));

        $this->assertSame((int) DB::table('tv_episodes')->where(['videos_id' => $videoId, 'episode' => 2])->value('id'), $first);
        $this->assertSame($first, $again);
        $this->assertNotSame(1, $first);
        $this->assertFalse($this->provider->addEpisode(0, $this->episode(1, 3)));
        $this->assertFalse($this->provider->addEpisode($videoId, $this->episode(0, 0)), 'An unnumbered episode without an airdate cannot be identified.');
    }

    public function test_add_episodes_stores_each_episode_once_including_undated_ones(): void
    {
        $videoId = $this->insertVideo('The Expanse');
        $episodes = [$this->episode(1, 1, '2015-12-14'), $this->episode(1, 2), $this->episode(0, 1, '2015-11-01')];

        $this->assertSame(3, $this->provider->addEpisodes($videoId, $episodes));
        $this->assertSame(0, $this->provider->addEpisodes($videoId, $episodes));
        $this->assertSame(3, DB::table('tv_episodes')->where('videos_id', $videoId)->count());
    }

    public function test_update_only_fills_empty_fields_and_keeps_two_letter_countries(): void
    {
        $videoId = $this->insertVideo('Severance', ['tvdb' => 371980, 'imdb' => '0']);
        DB::table('tv_info')->where('videos_id', $videoId)->update(['summary' => 'Existing summary']);

        $this->provider->update($videoId, [
            'country' => 'us',
            'tvdb' => 999,
            'tmdb' => 95396,
            'imdb' => 'tt11280740',
            'summary' => 'New summary',
            'publisher' => 'Apple TV+',
            'localzone' => "''",
            'aliases' => ['Separation', ['name' => 'Dissociation'], 'separation'],
        ]);

        $video = DB::table('videos')->find($videoId);
        $info = DB::table('tv_info')->where('videos_id', $videoId)->first();
        $this->assertSame(['US', 371980, 95396, '11280740'], [$video->countries_id, (int) $video->tvdb, (int) $video->tmdb, $video->imdb]);
        $this->assertSame(['Existing summary', 'Apple TV+', ''], [$info->summary, $info->publisher, $info->localzone]);
        $this->assertEqualsCanonicalizing(['Separation', 'Dissociation'], DB::table('videos_aliases')->where('videos_id', $videoId)->pluck('title')->all());
    }

    public function test_every_new_alias_of_a_show_is_stored(): void
    {
        $videoId = $this->insertVideo('Money Heist');

        $this->provider->addAliases($videoId, ['La Casa de Papel']);
        $this->provider->addAliases($videoId, ['Haus des Geldes', 'La Casa de Papel']);

        $this->assertSame(2, DB::table('videos_aliases')->where('videos_id', $videoId)->count());
        $this->assertNotFalse($this->provider->getAliases($videoId, 'Haus des Geldes'));
        $this->assertFalse($this->provider->getAliases($videoId, 'Unknown Alias'));
    }

    public function test_add_reuses_the_show_found_by_site_id_or_by_title_of_the_same_type(): void
    {
        $film = $this->insertVideo('Dune', ['type' => AbstractTvProvider::TYPE_FILM]);
        $tvId = $this->provider->add($this->show(['title' => 'Dune', 'tvdb' => 101]));
        $sameTvdb = $this->provider->add($this->show(['title' => 'Dune: Prophecy', 'tvdb' => 101]));
        $sameTitle = $this->provider->add($this->show(['title' => 'Dune', 'tmdb' => 202]));

        $this->assertNotSame($film, $tvId);
        $this->assertSame($tvId, $sameTvdb);
        $this->assertSame($tvId, $sameTitle);
        $this->assertSame(202, (int) DB::table('videos')->where('id', $tvId)->value('tmdb'));
        $this->assertSame(2, DB::table('videos')->count());
    }

    public function test_title_lookups_respect_type_and_prefer_the_tightest_match(): void
    {
        $this->insertVideo("Grey's Anatomy", ['type' => AbstractTvProvider::TYPE_FILM]);
        $tv = $this->insertVideo("Grey's Anatomy");
        $this->insertVideo('Law and Order Special Victims Unit');
        $tight = $this->insertVideo('Law and Order');

        $this->assertSame($tv, $this->provider->getAlternativeTitleExact('Greys Anatomy', AbstractTvProvider::TYPE_TV));
        $this->assertSame(0, $this->provider->getAlternativeTitleExact('Greys Anatomy', AbstractTvProvider::TYPE_ANIME));
        $this->assertSame($tight, $this->provider->getTitleLoose('%Law%Order%', AbstractTvProvider::TYPE_TV));
    }

    public function test_writer_does_not_overwrite_a_release_renamed_while_it_was_processed(): void
    {
        $releaseId = $this->insertTvRelease('Renamed.Meanwhile.S01E01');
        $context = new TvReleaseContext($releaseId, 'Old.Name.S01E01', 1, 5040);

        (new TvLookupOutcomeWriter)->record($context, TvProcessingResult::notFound());

        $this->assertSame(TvLookupStatus::Pending->value, (int) DB::table('releases')->where('id', $releaseId)->value('tv_episodes_id'));
    }

    public function test_writer_reindexes_only_when_the_show_or_episode_link_changes(): void
    {
        $videoId = $this->insertVideo('The Expanse');
        $episodeId = $this->insertEpisode($videoId, 1, 2);
        $missed = $this->insertTvRelease('Unknown.Show.S01E01');
        $matched = $this->insertTvRelease('The.Expanse.S01E02');
        $writer = new TvLookupOutcomeWriter;

        $writer->record(new TvReleaseContext($missed, 'Unknown.Show.S01E01', 1, 5040), TvProcessingResult::notFound());
        $writer->record(new TvReleaseContext($matched, 'The.Expanse.S01E02', 1, 5040), TvProcessingResult::matched($videoId, $episodeId, 'TVDB'));

        Search::getFacadeRoot()->shouldNotHaveReceived('updateRelease', [$missed]);
        Search::getFacadeRoot()->shouldHaveReceived('updateRelease')->with($matched)->once();
        $this->assertSame(TvLookupStatus::ShowNotFound->value, (int) DB::table('releases')->where('id', $missed)->value('tv_episodes_id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function episode(int $season, int $episode, string $firstAired = ''): array
    {
        return ['series' => $season, 'episode' => $episode, 'se_complete' => '', 'title' => 'Episode', 'firstaired' => $firstAired, 'summary' => ''];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function show(array $overrides): array
    {
        return [
            'type' => AbstractTvProvider::TYPE_TV, 'title' => '', 'summary' => '', 'started' => '2024-01-01', 'publisher' => '',
            'country' => '', 'source' => AbstractTvProvider::SOURCE_TVDB, 'imdb' => '', 'tvdb' => 0, 'trakt' => 0, 'tvrage' => 0,
            'tvmaze' => 0, 'tmdb' => 0, 'aliases' => '', 'localzone' => '',
            ...$overrides,
        ];
    }
}
