<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NameFixing\ConstrainedPredbMatcher;
use PHPUnit\Framework\TestCase;

class ConstrainedPredbMatcherTest extends TestCase
{
    public function test_partial_typo_recovers_content_suggestion_but_not_release_identity(): void
    {
        $hits = [['id' => 1, 'title' => 'Kanata.Kara.S01E02.1080p.AMZN.WEB-DL.JPN.DDP2.0.H.264-ToonsHub']];
        $matcher = new ConstrainedPredbMatcher;
        $this->assertSame(1, $matcher->suggest('kazata.kara.s1e2', $hits)['id']);
        $this->assertNull($matcher->completeEvidence('kazata.kara.s1e2', $hits)['id']);
        $this->assertSame(1, $matcher->completeEvidence('Kazata.Kara.S01E02.1080p.AMZN.WEB-DL.JPN.DDP2.0.H.264-ToonsHub', $hits)['id']);
    }

    public function test_wrong_episode_year_source_resolution_group_and_edition_are_rejected(): void
    {
        $matcher = new ConstrainedPredbMatcher;
        $query = 'Known.Movie.2026.S01E02.1080p.AMZN.WEB-DL.REPACK-GRP';
        foreach (['2026' => '2025', 'S01E02' => 'S01E03', '1080p' => '720p', 'AMZN' => 'DSNP', 'REPACK' => 'PROPER', '-GRP' => '-OTHER'] as $from => $to) {
            $this->assertNull($matcher->suggest($query, [['id' => 1, 'title' => str_replace((string) $from, $to, $query)]])['id']);
        }
    }

    public function test_neighboring_variants_do_not_resolve_partial_query(): void
    {
        $matcher = new ConstrainedPredbMatcher;
        $hits = [['id' => 1, 'title' => 'Known.Movie.2026.1080p.WEB-DL-GRP'], ['id' => 2, 'title' => 'Known.Movie.2026.720p.WEB-DL-GRP']];
        $this->assertNull($matcher->suggest('Known.Movie.2026', $hits)['id']);
        $this->assertNull($matcher->completeEvidence('Known.Movie.2026', [$hits[0]])['id']);
    }

    public function test_sample_and_cover_inside_a_title_are_semantic_words(): void
    {
        $matcher = new ConstrainedPredbMatcher;
        foreach (['Sample', 'Cover'] as $word) {
            $query = 'Known.'.$word.'.Movie.2026.1080p.WEB-DL-GRP';
            $this->assertNull($matcher->suggest($query, [['id' => 1, 'title' => 'Known.Movie.2026.1080p.WEB-DL-GRP']])['id']);
        }
    }

    public function test_candidate_overflow_and_numeric_sequels_abstain(): void
    {
        $matcher = new ConstrainedPredbMatcher;
        $this->assertNull($matcher->suggest('Known.Movie.2026', array_fill(0, 51, ['id' => 1, 'title' => 'Known.Movie.2026']))['id']);
        $this->assertNull($matcher->suggest('Known.Movie.2.2026', [['id' => 1, 'title' => 'Known.Movie.3.2026']])['id']);
    }
}
