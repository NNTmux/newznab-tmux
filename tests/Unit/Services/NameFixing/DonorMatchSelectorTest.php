<?php

declare(strict_types=1);

namespace Tests\Unit\Services\NameFixing;

use App\Services\NameFixing\DonorMatchSelector;
use PHPUnit\Framework\TestCase;

class DonorMatchSelectorTest extends TestCase
{
    public function test_it_selects_the_closest_donor_with_a_stable_id_tie_breaker(): void
    {
        $selector = new DonorMatchSelector;

        $selected = $selector->select([
            (object) ['releases_id' => 20, 'relsize' => 950],
            (object) ['releases_id' => 10, 'relsize' => 1050],
            (object) ['releases_id' => 30, 'relsize' => 900],
        ], 1000, 10);

        $this->assertNotNull($selected);
        $this->assertSame(10, $selected->releases_id);
    }

    public function test_it_rejects_zero_sized_and_out_of_tolerance_donors(): void
    {
        $selector = new DonorMatchSelector;

        $selected = $selector->select([
            (object) ['releases_id' => 10, 'relsize' => 0],
            (object) ['releases_id' => 20, 'relsize' => 500],
        ], 1000, 10);

        $this->assertNull($selected);
        $this->assertNull($selector->select([(object) ['releases_id' => 30, 'relsize' => 1000]], 0, 10));
    }
}
