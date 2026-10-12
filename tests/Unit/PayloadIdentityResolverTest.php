<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ReleaseCleaningService;
use App\Services\Releases\Verification\PayloadIdentityResolver;
use PHPUnit\Framework\TestCase;

class PayloadIdentityResolverTest extends TestCase
{
    public function test_verified_scene_file_supplies_the_masked_release_name(): void
    {
        $manifest = ['identity_hints' => [['name' => 'Known.Movie.2026.1080p.WEB-DL-GRP.mkv', 'source' => 'yenc']]];
        $this->assertSame('Known.Movie.2026.1080p.WEB-DL-GRP', (new PayloadIdentityResolver(new ReleaseCleaningService))->resolve($manifest));
    }

    public function test_separator_variants_agree_without_erasing_release_markers(): void
    {
        $manifest = ['identity_hints' => [['name' => 'Known_Movie_2026_1080p_WEB-DL-GRP.part01.rar', 'source' => 'yenc'],
            ['name' => 'Known.Movie.2026.1080p.WEB-DL-GRP.mkv', 'source' => 'par2']]];
        $this->assertNotNull((new PayloadIdentityResolver(new ReleaseCleaningService))->resolve($manifest));
        $manifest['identity_hints'][1]['name'] = 'Known.Movie.2026.720p.WEB-DL-GRP.mkv';
        $this->assertNull((new PayloadIdentityResolver(new ReleaseCleaningService))->resolve($manifest));
    }

    public function test_generic_hash_sample_and_competing_titles_remain_unresolved(): void
    {
        $resolver = new PayloadIdentityResolver(new ReleaseCleaningService);
        foreach (['e3cc3d848acd47ab920f5b830ba7be88.rar', 'Annual.Report.Final.txt', 'Known.Movie.2026.1080p-GRP.sample.mkv', 'sample.mkv'] as $name) {
            $this->assertNull($resolver->resolve(['identity_hints' => [['name' => $name, 'source' => 'yenc']]]));
        }
        $this->assertNull($resolver->resolve(['identity_hints' => [['name' => 'Film.One.2026.1080p-GRP.mkv', 'source' => 'yenc'], ['name' => 'Film.Two.2026.1080p-GRP.mkv', 'source' => 'par2']]]));
    }

    public function test_unverified_input_has_no_identity_hints(): void
    {
        $this->assertNull((new PayloadIdentityResolver(new ReleaseCleaningService))->resolve(['files' => [['decoded_name' => 'Known.Movie.2026.1080p-GRP.mkv']]]));
    }
}
