<?php

namespace Tests\Unit;

use App\Services\NameFixing\ReversedNameDetectorService;
use PHPUnit\Framework\TestCase;

class ReversedNameDetectorServiceTest extends TestCase
{
    private ReversedNameDetectorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReversedNameDetectorService;
    }

    public function test_detects_reversed_polizeiruf_name(): void
    {
        $reversed = '2D.20xoB011.furieziloP';
        $expected = 'Polizeiruf.110Box02.D2';
        $this->assertTrue($this->service->isReversed($reversed));
        $result = $this->service->decode($reversed);
        $this->assertTrue($result['decoded']);
        $this->assertSame($expected, $result['result']);
    }

    public function test_detects_reversed_tv_show_with_season_episode(): void
    {
        // Status.S01E01.HDTV.720p reversed
        $reversed = 'p027.VTDH.10E10S.sutatS';
        $result = $this->service->decode($reversed);
        $this->assertTrue($result['decoded']);
        $this->assertStringContainsString('Status', $result['result']);
    }

    public function test_does_not_reverse_valid_names(): void
    {
        $validNames = [
            'The.Walking.Dead.S11E01.720p.HDTV',
            'Avatar.2009.BluRay.1080p.x264',
            'Polizeiruf.110.S01E01.German.720p',
            'Game.of.Thrones.S08E06.HDTV',
        ];
        foreach ($validNames as $name) {
            $result = $this->service->decode($name);
            $this->assertFalse(
                $result['decoded'],
                "Expected '{$name}' to NOT be detected as reversed"
            );
        }
    }

    public function test_decode_returns_correct_structure(): void
    {
        $result = $this->service->decode('2D.20xoB011.furieziloP');
        $this->assertArrayHasKey('decoded', $result);
        $this->assertArrayHasKey('original', $result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    public function test_returns_confidence_levels(): void
    {
        // High confidence - clear pattern
        $result = $this->service->decode('2D.20xoB011.furieziloP');
        $this->assertContains($result['confidence'], ['high', 'medium', 'low']);
    }

    public function test_handles_short_strings(): void
    {
        $result = $this->service->decode('abc');
        $this->assertFalse($result['decoded']);
    }

    public function test_handles_empty_string(): void
    {
        $result = $this->service->decode('');
        $this->assertFalse($result['decoded']);
    }

    public function test_detects_reversed_quality_indicators(): void
    {
        // 720p reversed to p027
        $reversed = 'p027.VTDH.emaN.esaeleR';
        $this->assertTrue($this->service->isReversed($reversed));
    }

    public function test_detects_reversed_source_indicators(): void
    {
        // BluRay reversed to yaRulB
        $reversed = 'yaRulB.0202.eivoM.emoS';
        $this->assertTrue($this->service->isReversed($reversed));
    }

    public function test_cleans_decoded_names(): void
    {
        $reversed = '..2D.20xoB011.furieziloP..';
        $result = $this->service->decode($reversed);
        // Should not have leading/trailing dots
        $this->assertStringStartsNotWith('.', $result['result']);
        $this->assertStringEndsNotWith('.', $result['result']);
    }

    public function test_preserves_original_in_result(): void
    {
        $original = '2D.20xoB011.furieziloP';
        $result = $this->service->decode($original);

        $this->assertSame($original, $result['original']);
    }

    public function test_does_not_decode_hash_like_strings(): void
    {
        $hashes = [
            'wXwok9Ve6jvt2YwznEwqpC9XRKG8o5XzR',
            'KvrnJgoADdcZpex1A4X9AjSKCI5kryG',
            'hchq7nc59OvV9BST62RynCu0gvkEqTED',
            'abc123DEF456ghi789JKL',
        ];

        foreach ($hashes as $hash) {
            $result = $this->service->decode($hash);
            $this->assertFalse(
                $result['decoded'],
                "Expected hash '{$hash}' to NOT be decoded as reversed"
            );
        }
    }

    public function test_does_not_detect_hash_as_reversed(): void
    {
        $hashes = [
            'wXwok9Ve6jvt2YwznEwqpC9XRKG8o5XzR',
            'KvrnJgoADdcZpex1A4X9AjSKCI5kryG',
        ];

        foreach ($hashes as $hash) {
            $this->assertFalse(
                $this->service->isReversed($hash),
                "Expected hash '{$hash}' to NOT be detected as reversed"
            );
        }
    }
}
