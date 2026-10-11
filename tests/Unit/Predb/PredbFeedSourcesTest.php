<?php

declare(strict_types=1);

namespace Tests\Unit\Predb;

use App\Models\Predb;
use App\Services\Predb\Feeds\PredbFeedEntry;
use App\Services\Predb\Feeds\Sources\ApibaySource;
use App\Services\Predb\Feeds\Sources\PredbClubSource;
use App\Services\Predb\Feeds\Sources\PredbNetSource;
use App\Services\Predb\Feeds\Sources\PredbOrgSource;
use App\Services\Predb\Feeds\Sources\PredbOvhSource;
use App\Services\Predb\Feeds\Sources\RssFeedSource;
use App\Services\Predb\Feeds\Sources\SrrdbSource;
use App\Services\Predb\Feeds\Sources\XrelSource;
use App\Support\IrcChannelList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PredbFeedSourcesTest extends TestCase
{
    #[Test]
    public function predb_club_rows_are_normalised(): void
    {
        $entries = (new PredbClubSource('https://predb.club/api/v1/'))->parseRows($this->json('predb_club.json')['data']['rows']);

        $this->assertCount(3, $entries);
        $this->assertSame('WAR.2026.S01E02.FRENCH.WEB.H264-BAWLS', $entries[0]->title);
        $this->assertSame('predb.club', $entries[0]->source);
        $this->assertSame('TV-SD-FR', $entries[0]->category);
        $this->assertSame('457MB', $entries[0]->size);
        $this->assertSame('32', $entries[0]->files);
        $this->assertSame(1791513593, $entries[0]->predate?->getTimestamp());
        $this->assertSame(Predb::PRE_NONUKE, $entries[0]->nuked);
    }

    #[Test]
    public function predb_club_zero_size_and_files_are_unknown_and_nukes_are_mapped(): void
    {
        $entries = (new PredbClubSource('https://predb.club/api/v1/'))->parseRows($this->json('predb_club.json')['data']['rows']);

        $this->assertNull($entries[1]->size);
        $this->assertNull($entries[1]->files);
        $this->assertSame('2150.5MB', $entries[2]->size);
        $this->assertSame(Predb::PRE_NUKED, $entries[2]->nuked);
        $this->assertSame('dupe.2026-10-01', $entries[2]->nukeReason);
    }

    #[Test]
    public function predb_net_rows_are_normalised_with_status_mapping(): void
    {
        $entries = (new PredbNetSource('https://api.predb.net/'))->parseRows($this->json('predb_net.json')['data']);

        $this->assertCount(2, $entries);
        $this->assertSame('predb.net', $entries[0]->source);
        $this->assertSame('TV-WEB-X264', $entries[0]->category);
        $this->assertNull($entries[0]->size);
        $this->assertNull($entries[0]->nukeReason);
        $this->assertSame(Predb::PRE_NUKED, $entries[1]->nuked);
        $this->assertSame('bad.ivtc', $entries[1]->nukeReason);
        $this->assertSame('850MB', $entries[1]->size);
    }

    #[Test]
    public function rss_items_keep_release_titles_and_parse_pub_dates_as_utc(): void
    {
        $source = new RssFeedSource('predb_me', 'predb.me', 'https://predb.me/?rss=1');
        $entries = $source->parseXml((string) file_get_contents($this->fixture('predb_me.xml')));

        $this->assertSame(['Nybble_and_Nibble_CD32_AMIGA-bADkARMA', 'Elsbeth.S04E01.1080p.HDTV.x264-SYNCOPY'], array_map(static fn (PredbFeedEntry $e): string => $e->title, $entries));
        $this->assertNull($entries[0]->predate);
        $this->assertSame('2026-10-09 02:37:37', $entries[1]->predate?->format('Y-m-d H:i:s'));
        $this->assertSame('predb.me', $entries[1]->source);
    }

    #[Test]
    public function malformed_rss_is_rejected_but_an_empty_channel_is_not(): void
    {
        $source = new RssFeedSource('predb_me', 'predb.me', 'https://predb.me/?rss=1');

        $this->assertSame([], $source->parseXml('<?xml version="1.0"?><rss version="2.0"><channel><title>x</title></channel></rss>'));

        $this->expectException(\RuntimeException::class);
        $source->parseXml('<html>not rss');
    }

    #[Test]
    public function xrel_nuke_flags_mark_releases_nuked(): void
    {
        $entries = (new XrelSource('https://api.xrel.to/v2/release/latest.json'))->parseRows([
            ['dirname' => 'Nuked.Release-GRP', 'time' => 1, 'flags' => ['nuke_rls' => true]],
            ['dirname' => 'Clean.Release-GRP', 'time' => 1, 'flags' => ['english' => true]],
        ]);

        $this->assertSame(Predb::PRE_NUKED, $entries[0]->nuked);
        $this->assertSame(Predb::PRE_NONUKE, $entries[1]->nuked);
    }

    #[Test]
    public function srrdb_rows_keep_the_upload_time_for_paging_only_and_no_size(): void
    {
        $entries = (new SrrdbSource('https://api.srrdb.com/v1/search/order:date-desc'))->parseRows($this->json('srrdb.json')['results']);

        $this->assertCount(2, $entries);
        $this->assertSame('srrdb', $entries[1]->source);
        $this->assertSame('The.Celebrity.Traitors.S02E04.1080p.HDTV.H264-DARKFLiX', $entries[1]->title);
        // srrDB's date is when the SRR was added (Europe/Brussels), not the PRE time.
        $this->assertNull($entries[1]->predate);
        $this->assertSame('2026-10-09 20:12:51', $entries[1]->listedAt?->format('Y-m-d H:i:s'));
        // srrDB sizes can describe only an NFO, so they are not used as release sizes.
        $this->assertNull($entries[1]->size);
    }

    #[Test]
    public function srrdb_pages_use_the_skip_offset(): void
    {
        $this->assertSame('https://api.srrdb.com/v1/search/order:date-desc', SrrdbSource::pageUrl('https://api.srrdb.com/v1/search/order:date-desc', 1));
        $this->assertSame('https://api.srrdb.com/v1/search/order:date-desc/skip:90', SrrdbSource::pageUrl('https://api.srrdb.com/v1/search/order:date-desc/', 3));
    }

    #[Test]
    public function predb_org_rows_map_section_size_and_time(): void
    {
        $entries = (new PredbOrgSource('https://predb.org/api/search'))->parseRows($this->json('predb_org.json')['values']);

        $this->assertCount(3, $entries);
        $this->assertSame('Traqueur.De.Chars.S03E09.DOC.FRENCH.1080p.WEB.H264-BAWLS', $entries[0]->title);
        $this->assertSame('predb.org', $entries[0]->source);
        $this->assertSame('PRE', $entries[0]->category);
        $this->assertSame('1099MB', $entries[0]->size);
        $this->assertSame('24', $entries[0]->files);
        $this->assertSame(Predb::PRE_NONUKE, $entries[0]->nuked);
        $this->assertSame('FLAC-WEB', $entries[2]->category);
    }

    #[Test]
    public function predb_org_rows_with_a_nuke_reason_are_nuked_and_nameless_rows_are_skipped(): void
    {
        $entries = (new PredbOrgSource('https://predb.org/api/search'))->parseRows([
            ['rlsname' => 'Some.Show.S01E01.720p.HDTV.x264-GRP', 'section' => 'TV', 'ctime' => 1791500000, 'size' => 0, 'files' => 0, 'nukereason' => 'dupe.Other.Show-GRP'],
            ['rlsname' => '', 'section' => 'TV'],
            'not a row',
        ]);

        $this->assertCount(1, $entries);
        $this->assertSame(Predb::PRE_NUKED, $entries[0]->nuked);
        $this->assertSame('dupe.Other.Show-GRP', $entries[0]->nukeReason);

        $byStatus = (new PredbOrgSource('https://predb.org/api/search'))->parseRows([
            ['rlsname' => 'Nuked.Without.Reason-GRP', 'status' => '1', 'nukereason' => ''],
            ['rlsname' => 'Normal.Release-GRP', 'status' => '0', 'nukereason' => ''],
        ]);
        $this->assertSame(Predb::PRE_NUKED, $byStatus[0]->nuked);
        $this->assertNull($byStatus[0]->nukeReason);
        $this->assertSame(Predb::PRE_NONUKE, $byStatus[1]->nuked);
        $this->assertNull($entries[0]->size);
        $this->assertNull($entries[0]->files);
    }

    #[Test]
    public function scnsrc_titles_are_dotted_and_the_first_category_is_kept(): void
    {
        $source = new RssFeedSource('scnsrc_me', 'scnsrc.me', 'https://www.scnsrc.me/feed', spacedTitles: true, mapCategory: true);
        $entries = $source->parseXml((string) file_get_contents($this->fixture('scnsrc_me.xml')));

        $this->assertCount(2, $entries);
        $this->assertSame('Show.Name.S01E01.720p.WEB.h264-GROUP', $entries[0]->title);
        $this->assertSame('scnsrc.me', $entries[0]->source);
        $this->assertSame('TV', $entries[0]->category);
        $this->assertSame('2026-10-09 02:37:37', $entries[0]->predate?->format('Y-m-d H:i:s'));
        $this->assertNull($entries[1]->category);
    }

    #[Test]
    public function eztv_titles_are_dotted_and_the_site_tag_is_dropped(): void
    {
        $source = new RssFeedSource('eztv', 'eztv', 'https://eztvx.to/ezrss.xml', spacedTitles: true);
        $entries = $source->parseXml((string) file_get_contents($this->fixture('eztv.xml')));

        $this->assertCount(1, $entries);
        $this->assertSame('Show.Name.S02E03.1080p.WEB.H264-GROUP', $entries[0]->title);
        $this->assertSame('eztv', $entries[0]->source);
        $this->assertNull($entries[0]->category);
    }

    #[Test]
    public function predb_ovh_rows_are_normalised_with_status_mapping(): void
    {
        $entries = (new PredbOvhSource('https://predb.ovh/api/v1/'))->parseRows($this->json('predb_ovh.json')['data']['rows']);

        $this->assertCount(3, $entries);
        $this->assertSame('predb.ovh', $entries[0]->source);
        $this->assertSame('TV-WEB-X264', $entries[0]->category);
        $this->assertSame('457MB', $entries[0]->size);
        $this->assertSame('32', $entries[0]->files);
        $this->assertSame(1791513593, $entries[0]->predate?->getTimestamp());
        $this->assertNull($entries[1]->size);
        $this->assertSame(Predb::PRE_NUKED, $entries[2]->nuked);
        $this->assertSame('dupe.2026-10-01', $entries[2]->nukeReason);
    }

    #[Test]
    public function apibay_keeps_only_movie_and_tv_categories_and_converts_bytes(): void
    {
        $entries = (new ApibaySource('https://apibay.org/precompiled/data_top100_recent.json'))->parseRows($this->json('apibay.json'));

        $this->assertSame(['Some.Show.S01E01.1080p.WEB.h264-GRP', 'Some.Movie.2026.720p.BluRay.x264-GRP'], array_map(static fn (PredbFeedEntry $e): string => $e->title, $entries));
        $this->assertSame('apibay', $entries[0]->source);
        $this->assertSame('TV-HD', $entries[0]->category);
        $this->assertSame('MOVIE-HD', $entries[1]->category);
        $this->assertSame('1024MB', $entries[0]->size);
        $this->assertSame('3', $entries[0]->files);
        $this->assertNull($entries[1]->files);
        $this->assertSame(1791513593, $entries[0]->predate?->getTimestamp());
    }

    #[Test]
    public function xrel_scene_rows_map_category_size_and_time(): void
    {
        $entries = (new XrelSource('https://api.xrel.to/v2/release/latest.json'))->parseRows($this->json('xrel.json')['list']);

        $this->assertCount(2, $entries);
        $this->assertSame('xrel', $entries[0]->source);
        $this->assertSame('The.Celebrity.Traitors.S02E04.1080p.HDTV.H264-DARKFLiX', $entries[0]->title);
        $this->assertSame('TV', $entries[0]->category);
        $this->assertSame('2371MB', $entries[0]->size);
        $this->assertSame(1791576763, $entries[0]->predate?->getTimestamp());
        $this->assertSame('MOVIE', $entries[1]->category);
    }

    #[Test]
    public function xrel_p2p_rows_map_category_size_and_time(): void
    {
        $entries = (new XrelSource('https://api.xrel.to/v2/p2p/releases.json', p2p: true))->parseRows($this->json('xrel_p2p.json')['list']);

        $this->assertCount(2, $entries);
        $this->assertSame('xrel-p2p', $entries[0]->source);
        $this->assertSame('Animals.2026.2160p.NF.WEB-DL.DDP5.1.Atmos.DV.HDR.H.265-Kitsune', $entries[0]->title);
        $this->assertSame('MOVIE-HD-2160p', $entries[0]->category);
        $this->assertSame('12200MB', $entries[0]->size);
        $this->assertSame(1791576306, $entries[0]->predate?->getTimestamp());
    }

    #[Test]
    public function sizes_under_one_megabyte_are_reported_in_kilobytes(): void
    {
        $this->assertSame('4KB', PredbFeedEntry::sizeFromMegabytes(4096 / 1048576));
        $this->assertSame('119.53KB', PredbFeedEntry::sizeFromMegabytes(122400 / 1048576));
        $this->assertSame('1MB', PredbFeedEntry::sizeFromMegabytes(1.0));
    }

    #[Test]
    public function undelpre_statuses_map_to_unnuked(): void
    {
        $net = (new PredbNetSource('https://api.predb.net/'))->parseRows([
            ['release' => 'A-GRP', 'status' => 4, 'reason' => 'restored'],
            ['release' => 'B-GRP', 'status' => 3, 'reason' => 'dupe'],
        ]);
        $club = (new PredbClubSource('https://predb.club/api/v1/'))->parseRows([
            ['name' => 'C-GRP', 'nuke' => ['type' => 'undelpre', 'reason' => 'restored']],
            ['name' => 'D-GRP', 'nuke' => ['type' => 'delpre', 'reason' => 'gone']],
        ]);

        $this->assertSame(Predb::PRE_UNNUKED, $net[0]->nuked);
        $this->assertSame(Predb::PRE_NUKED, $net[1]->nuked);
        $this->assertSame(Predb::PRE_UNNUKED, $club[0]->nuked);
        $this->assertSame(Predb::PRE_NUKED, $club[1]->nuked);
    }

    #[Test]
    public function xrel_symbolic_size_units_are_not_treated_as_megabytes(): void
    {
        $entries = (new XrelSource('https://api.xrel.to/v2/release/latest.json'))->parseRows([
            ['dirname' => 'Rar.Only-GRP', 'time' => 1, 'size' => ['number' => 1, 'unit' => 'RAR']],
            ['dirname' => 'Big.One-GRP', 'time' => 1, 'size' => ['number' => 2, 'unit' => 'GB']],
        ]);

        $this->assertNull($entries[0]->size);
        $this->assertSame('2048MB', $entries[1]->size);
    }

    #[Test]
    public function irc_channel_lists_parse_names_and_optional_passwords(): void
    {
        $this->assertSame(['#PreNNTmux' => null, '#nZEDbPRE' => null, '#secret' => 'pw'], IrcChannelList::parse(' #PreNNTmux, nZEDbPRE ,#secret:pw,, '));
        $this->assertSame(['#PreNNTmux' => null], IrcChannelList::parse(''));
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $name): array
    {
        return json_decode((string) file_get_contents($this->fixture($name)), true, flags: JSON_THROW_ON_ERROR);
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__, 2).'/Fixtures/predb/'.$name;
    }
}
