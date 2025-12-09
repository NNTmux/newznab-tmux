<?php

namespace App\Console\Commands;

use App\Services\Categorization\CategorizationService;
use Illuminate\Console\Command;

class TestCategorization extends Command
{
    protected $signature = 'nntmux:test-categorization
                            {--release= : Release name to test}
                            {--compare : Compare pipeline vs legacy categorizer}
                            {--list-categorizers : List all registered categorizers}';

    protected $description = 'Test the new pipeline-based categorization service';

    public function handle(): int
    {
        $service = new CategorizationService();

        if ($this->option('list-categorizers')) {
            $this->listCategorizers($service);
            return 0;
        }

        $releaseName = $this->option('release');

        if (!$releaseName) {
            // Test with sample releases
            $this->testSampleReleases($service);
            return 0;
        }

        if ($this->option('compare')) {
            $this->compareResult($service, $releaseName);
        } else {
            $this->categorizeRelease($service, $releaseName);
        }

        return 0;
    }

    protected function listCategorizers(CategorizationService $service): void
    {
        $stats = $service->getCategorizerStats();

        $this->info('Registered Categorizers:');
        $this->table(
            ['Name', 'Priority', 'Class'],
            collect($stats)->map(fn ($s) => [$s['name'], $s['priority'], $s['class']])->toArray()
        );
    }

    protected function categorizeRelease(CategorizationService $service, string $releaseName): void
    {
        $result = $service->determineCategory(0, $releaseName, '', true);

        $this->info("Release: {$releaseName}");
        $this->info("Category ID: {$result['categories_id']}");

        if (isset($result['debug'])) {
            $this->info("Matched By: {$result['debug']['matched_by']}");
            $this->info("Confidence: " . ($result['debug']['final_confidence'] ?? 'N/A'));
        }
    }

    protected function compareResult(CategorizationService $service, string $releaseName): void
    {
        $comparison = $service->compare(0, $releaseName);

        $this->info("Release: {$releaseName}");
        $this->newLine();

        $matchStatus = $comparison['match'] ? '<fg=green>MATCH</>' : '<fg=red>MISMATCH</>';
        $this->line("Result: {$matchStatus}");
        $this->newLine();

        $this->table(
            ['', 'Pipeline', 'Legacy'],
            [
                ['Category ID', $comparison['pipeline']['category_id'], $comparison['legacy']['category_id']],
                ['Category Name', $comparison['pipeline']['category_name'], $comparison['legacy']['category_name']],
            ]
        );
    }

    protected function testSampleReleases(CategorizationService $service): void
    {
        $samples = [
            // TV
            'Game.of.Thrones.S08E06.720p.BluRay.x264-DEMAND',
            'The.Mandalorian.S03E08.2160p.WEB-DL.DDP5.1.Atmos.H.265-FLUX',
            '[SubsPlease] Jujutsu Kaisen - 47 (1080p) [ABC12345].mkv',

            // Movies
            'The.Matrix.Resurrections.2021.2160p.UHD.BluRay.x265-SURCODE',
            'Inception.2010.1080p.BluRay.x264-SPARKS',
            'Oppenheimer.2023.WEB-DL.1080p.H.264.AAC-LOL',

            // XXX
            'Brazzers.23.11.15.Model.Name.XXX.1080p.MP4-KTR',
            'SexBabesVR.23.10.20.Virtual.Reality.VR180.3D.SBS.2160p-VRSins',

            // Games
            'Cyberpunk.2077.Ultimate.Edition.v2.12.1-RUNE',
            'Elden.Ring.Shadow.of.the.Erdtree.PS5-DUPLEX',

            // Music
            'Taylor.Swift.Midnights.2022.FLAC-dL',
            'Various.Artists.Now.Thats.What.I.Call.Music.100.2018.MP3.320kbps',

            // Books
            'Brandon.Sanderson.Mistborn.Trilogy.EPUB',
            'OReilly.Learning.Python.6th.Edition.PDF',
        ];

        $this->info('Testing sample releases with both pipeline and legacy categorizers:');
        $this->newLine();

        $results = [];
        foreach ($samples as $sample) {
            $comparison = $service->compare(0, $sample);
            $results[] = [
                substr($sample, 0, 50) . (strlen($sample) > 50 ? '...' : ''),
                $comparison['pipeline']['category_name'],
                $comparison['legacy']['category_name'],
                $comparison['match'] ? '✓' : '✗',
            ];
        }

        $this->table(
            ['Release', 'Pipeline', 'Legacy', 'Match'],
            $results
        );
    }
}

