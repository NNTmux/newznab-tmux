<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Tmux\Tmux;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Unit\ImdbScraperTestCase;

class TmuxTest extends ImdbScraperTestCase
{
    #[Test]
    public function it_counts_only_pending_movie_imdb_states_in_tmux_queries(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'lookupimdb'],
            ['value' => '1']
        );

        $tmux = new Tmux;

        $query = $tmux->proc_query(1, '7010', 'testing');

        $this->assertIsString($query);
        $this->assertStringContainsString("imdbid IS NULL OR imdbid IN ('0', '0000000', '00000000')", $query);
        $this->assertStringNotContainsString("imdbid IN ('', '0', '0000000', '00000000')", $query);
    }

    #[Test]
    public function it_counts_only_renamed_movie_work_when_lookupimdb_is_set_to_renamed_only(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'lookupimdb'],
            ['value' => '2']
        );

        $tmux = new Tmux;

        $query = $tmux->proc_query(1, '7010', 'testing');

        $this->assertIsString($query);
        $this->assertStringContainsString("imdbid IS NULL OR imdbid IN ('0', '0000000', '00000000')", $query);
        $this->assertStringContainsString('AND isrenamed = 1', $query);
    }
}
