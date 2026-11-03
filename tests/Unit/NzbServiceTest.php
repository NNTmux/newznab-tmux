<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Release;
use App\Services\Nzb\NzbService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class NzbServiceTest extends TestCase
{
    public function test_build_binary_subject_preserves_separator_for_quoted_names(): void
    {
        $reflection = new ReflectionClass(NzbService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildBinarySubject');

        $subject = $method->invoke($service, '"Example.Release.part01.rar" yEnc', 12);

        $this->assertSame('"Example.Release.part01.rar" yEnc (1/12)', $subject);
        $this->assertStringNotContainsString('" yEnc(1/12)', $subject);
        $this->assertStringNotContainsString('"(1/12)', $subject);
    }

    public function test_build_binary_subject_trims_trailing_whitespace_before_part_suffix(): void
    {
        $reflection = new ReflectionClass(NzbService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildBinarySubject');

        $subject = $method->invoke($service, 'Example.Release.rar yEnc   ', 3);

        $this->assertSame('Example.Release.rar yEnc (1/3)', $subject);
    }

    public function test_normalize_segment_message_id_strips_outer_quotes_and_brackets(): void
    {
        $reflection = new ReflectionClass(NzbService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('normalizeSegmentMessageId');

        $messageId = $method->invoke($service, '"<part01.abcd@example.test>"');

        $this->assertSame('part01.abcd@example.test', $messageId);
    }

    public function test_normalize_segment_message_id_keeps_bare_message_id_as_is(): void
    {
        $reflection = new ReflectionClass(NzbService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('normalizeSegmentMessageId');

        $messageId = $method->invoke($service, 'part01.abcd@example.test');

        $this->assertSame('part01.abcd@example.test', $messageId);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function metaNameProvider(): array
    {
        $wrapped = '[80/98] "MLB__NZBSPLIT__d5e69bbf16e52f8bc43063b814526b7f__NZBSPLIT__MLB_RS_2026_Detroit_Tigers_vs_Chicago_White_Sox_20_09_720pEN60fps_DSN.7z.080" yEnc';

        return [
            'nzbsplit uses the clean searchname' => [$wrapped, 'MLB.RS.2026.Detroit.Tigers.vs.Chicago.White.Sox.20.09.720pEN60fps.DSN.PreDB', 'MLB.RS.2026.Detroit.Tigers.vs.Chicago.White.Sox.20.09.720pEN60fps.DSN.PreDB'],
            'nzbsplit with a wrapped searchname uses the parsed title' => [$wrapped, $wrapped, 'MLB.RS.2026.Detroit.Tigers.vs.Chicago.White.Sox.20.09.720pEN60fps.DSN'],
            'ordinary release keeps its name' => ['[01/20] - "Some.Release.part01.rar" yEnc', 'Some.Release', '[01/20] - "Some.Release.part01.rar" yEnc'],
        ];
    }

    #[DataProvider('metaNameProvider')]
    public function test_meta_name_uses_clean_title_for_nzb_split_releases(string $name, string $searchName, string $expected): void
    {
        $service = (new ReflectionClass(NzbService::class))->newInstanceWithoutConstructor();
        $release = (new Release)->forceFill(['name' => $name, 'searchname' => $searchName]);

        $this->assertSame($expected, $service->metaName($release));
    }
}
