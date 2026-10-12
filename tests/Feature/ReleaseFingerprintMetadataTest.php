<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Releases\Fingerprint\NzbFingerprintAnalyzer;
use App\Services\Releases\Fingerprint\Par2MetadataParser;
use App\Services\YencService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReleaseFingerprintMetadataTest extends TestCase
{
    #[Test]
    public function it_lists_every_nzb_file_and_calculates_advertised_parity_ratio(): void
    {
        $xml = <<<'XML'
        <nzb xmlns="http://www.newzbin.com/DTD/2003/nzb">
          <head><meta type="category">Movies</meta></head>
          <file subject="archive.part01.rar" poster="poster@example.net" date="100"><groups><group>alt.test</group></groups><segments><segment bytes="100" number="1">one@example.net</segment><segment bytes="100" number="2">two@example.net</segment></segments></file>
          <file subject="archive.part02.rar" poster="poster@example.net" date="100"><groups><group>alt.test</group></groups><segments><segment bytes="200" number="1">three@example.net</segment></segments></file>
          <file subject="archive.vol00+01.par2" poster="poster@example.net" date="100"><groups><group>alt.test</group></groups><segments><segment bytes="50" number="1">four@example.net</segment></segments></file>
        </nzb>
        XML;

        $report = (new NzbFingerprintAnalyzer(new YencService))->analyzeManifest($xml);

        self::assertSame(3, $report['file_count']);
        self::assertCount(3, $report['files']);
        self::assertSame(200, $report['files'][0]['advertised_bytes']);
        self::assertSame(300, $report['totals']['rar']['advertised_bytes']);
        self::assertSame(50, $report['totals']['par2']['advertised_bytes']);
        self::assertSame(16.667, $report['par2_to_rar_advertised_percent']);
        self::assertSame(['Movies'], $report['nzb_meta_values']['category']);
    }

    #[Test]
    public function it_samples_first_middle_and_last_file_segments_and_keeps_all_par2_segments(): void
    {
        $files = [
            ['index' => 0, 'kind' => 'rar', 'subject' => 'one.rar', 'segments' => [
                ['number' => 1, 'messageid' => 'one-a@example.net'],
                ['number' => 2, 'messageid' => 'one-b@example.net'],
                ['number' => 3, 'messageid' => 'one-c@example.net'],
            ]],
            ['index' => 1, 'kind' => 'par2', 'subject' => 'set.par2', 'segments' => [
                ['number' => 1, 'messageid' => 'par-a@example.net'],
                ['number' => 2, 'messageid' => 'par-b@example.net'],
            ]],
        ];

        $samples = (new NzbFingerprintAnalyzer(new YencService))->selectArticleSamples($files);

        self::assertSame(['one-a@example.net', 'one-b@example.net', 'one-c@example.net', 'par-a@example.net', 'par-b@example.net'], array_column($samples, 'messageid'));
        self::assertSame(['first', 'middle', 'last', 'all_par2_segments', 'all_par2_segments'], array_column($samples, 'sample_role'));
    }

    #[Test]
    public function it_reads_par2_file_description_offsets_and_recovery_denominator(): void
    {
        $fileId = str_repeat("F", 16);
        $main = pack('V2', 4096, 0).pack('V', 1).$fileId;
        $fileDescription = $fileId.str_repeat("M", 16).str_repeat("N", 16).pack('V2', 5000).'payload.rar'."\0";
        $recovery = pack('V', 0).str_repeat("R", 4096);
        $binary = $this->par2Packet("PAR 2.0\0Main", $main)
            .$this->par2Packet("PAR 2.0\0FileDesc", $fileDescription)
            .$this->par2Packet("PAR 2.0\0RecvSlic", $recovery);

        $metadata = (new Par2MetadataParser)->parse($binary);

        self::assertSame(5000, $metadata['source_files'][0]['file_length']);
        self::assertSame('payload.rar', $metadata['source_files'][0]['filename']);
        self::assertSame(2, $metadata['source_block_count']);
        self::assertSame(1, $metadata['unique_recovery_slice_count']);
        self::assertSame(50.0, $metadata['recovery_to_source_percent']);
    }

    #[Test]
    public function it_preserves_raw_header_order_and_extracts_yenc_frame_details(): void
    {
        $rawBody = (new YencService)->encode('sample payload', 'archive.part01.rar', 128);
        $headers = [
            'Message-Id: <part@example.net>',
            'User-Agent: Nyuu/0.4.2',
            'Injection-Info: news.example.net',
        ];

        $article = (new NzbFingerprintAnalyzer(new YencService))->analyzeArticle($headers, $rawBody);

        self::assertSame($headers, $article['raw_headers']);
        self::assertSame(['Message-Id', 'User-Agent', 'Injection-Info'], $article['header_profile']['ordered_names']);
        self::assertSame('Nyuu/0.4.2', $article['headers']['user-agent']);
        self::assertSame('archive.part01.rar', $article['yenc']['name']);
        self::assertSame(14, $article['yenc']['decoded_segment_size']);
        self::assertNotEmpty($article['article_body_sha256']);
    }

    private function par2Packet(string $type, string $payload): string
    {
        $type = str_pad($type, 16, "\0");
        $length = 64 + strlen($payload);
        $packet = "PAR2\0PKT".pack('V2', $length, 0).str_repeat("\0", 16).str_repeat("S", 16).$type.$payload;

        return substr($packet, 0, 16).md5(substr($packet, 32), true).substr($packet, 32);
    }
}
