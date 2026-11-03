<?php

namespace Tests\Unit;

use App\Services\NameFixing\ArtOfUsenetCleanerService;
use PHPUnit\Framework\TestCase;

class ArtOfUsenetCleanerServiceTest extends TestCase
{
    private ArtOfUsenetCleanerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ArtOfUsenetCleanerService;
    }

    public function test_detects_art_of_usenet_releases(): void
    {
        // Should detect Art-of-Usenet patterns
        $this->assertTrue($this->service->isArtOfUsenetRelease(
            '2D.20xoB011.furieziloP [Art-of-Usenet] [Password] "2D.20xoB011.furieziloP.par2"'
        ));

        $this->assertTrue($this->service->isArtOfUsenetRelease(
            'Some.Release [art-of-usenet] "filename.rar"'
        ));

        // Should not detect other patterns
        $this->assertFalse($this->service->isArtOfUsenetRelease(
            'Normal.Release.Name.S01E01.720p.HDTV'
        ));

        $this->assertFalse($this->service->isArtOfUsenetRelease(
            '[House-of-Usenet] Some Release'
        ));
    }

    public function test_detects_password_indicator(): void
    {
        $this->assertTrue($this->service->hasPasswordIndicator(
            '2D.20xoB011.furieziloP [Art-of-Usenet] [Password] "filename.rar"'
        ));

        $this->assertTrue($this->service->hasPasswordIndicator(
            'Some.Release [password] "filename.rar"'
        ));

        $this->assertFalse($this->service->hasPasswordIndicator(
            'Normal.Release [Art-of-Usenet] "filename.rar"'
        ));
    }

    public function test_decodes_reversed_release_name(): void
    {
        $subject = '2D.20xoB011.furieziloP [Art-of-Usenet] [Password] "2D.20xoB011.furieziloP.par2"';
        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertTrue($result['hasPassword']);
        $this->assertSame('2D.20xoB011.furieziloP', $result['originalObfuscated']);
        $this->assertSame('Polizeiruf.110Box02.D2', $result['name']);
    }

    public function test_decodes_various_reversed_names(): void
    {
        // Test case: Tatort reversed
        $subject1 = 'trotatT.1202.NREIF [Art-of-Usenet] [Password] "filename.rar"';
        $result1 = $this->service->processSubject($subject1);
        $this->assertTrue($result1['matched']);
        // "NREIF.1202.Ttatort" reversed would be the result

        // Test case: Movie with year
        $subject2 = 'pirT.daoR.0202 [Art-of-Usenet] [Password] "file.rar"';
        $result2 = $this->service->processSubject($subject2);
        $this->assertTrue($result2['matched']);
    }

    public function test_process_subject_returns_correct_structure(): void
    {
        $subject = 'Obfuscated.Name [Art-of-Usenet] [Password] "file.rar"';
        $result = $this->service->processSubject($subject);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('hasPassword', $result);
        $this->assertArrayHasKey('matched', $result);
        $this->assertArrayHasKey('originalObfuscated', $result);
        $this->assertTrue($result['matched']);
        $this->assertTrue($result['hasPassword']);
    }

    public function test_non_art_of_usenet_returns_not_matched(): void
    {
        $subject = 'Normal.Release.S01E01.720p.HDTV.x264-GROUP';
        $result = $this->service->processSubject($subject);

        $this->assertFalse($result['matched']);
        $this->assertNull($result['name']);
        $this->assertFalse($result['hasPassword']);
        $this->assertNull($result['originalObfuscated']);
    }

    public function test_handles_release_without_password_indicator(): void
    {
        $subject = 'Obfuscated.Name [Art-of-Usenet] "file.rar"';
        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        $this->assertFalse($result['hasPassword']);
    }

    public function test_extracts_name_from_various_patterns(): void
    {
        // Name at the start
        $subject1 = 'emanesaeleR [Art-of-Usenet] [Password] "file.rar"';
        $result1 = $this->service->processSubject($subject1);
        $this->assertTrue($result1['matched']);
        $this->assertNotNull($result1['originalObfuscated']);

        // Name in quotes with extension
        $subject2 = '[Art-of-Usenet] [Password] "emanesaeleR.par2" - [001/100]';
        $result2 = $this->service->processSubject($subject2);
        $this->assertTrue($result2['matched']);
    }

    public function test_cleans_file_extensions_from_name(): void
    {
        $subject = '2D.20xoB011.furieziloP [Art-of-Usenet] [Password] "2D.20xoB011.furieziloP.vol001+002.par2"';
        $result = $this->service->processSubject($subject);

        $this->assertTrue($result['matched']);
        // The name should not contain file extensions
        $this->assertStringNotContainsString('.par2', $result['name'] ?? '');
        $this->assertStringNotContainsString('.vol', $result['name'] ?? '');
    }
}
