<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Composers\GlobalDataComposer;
use ReflectionClass;
use Tests\TestCase;

final class AdminAnidbControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setGlobalViewData([
            'serverroot' => 'http://localhost',
            'site' => ['dereferrer_link' => ''],
            'usefulLinks' => collect(),
            'isadmin' => true,
            'ismod' => false,
            'loggedin' => false,
            'userTheme' => 'light',
            'userColorScheme' => 'blue',
        ]);
    }

    protected function tearDown(): void
    {
        $this->setGlobalViewData(null);

        parent::tearDown();
    }

    public function test_edit_view_renders_database_result_object(): void
    {
        $anime = (object) [
            'anidbid' => 123,
            'title' => 'Regression Test Anime',
            'type' => 'TV Series',
            'startdate' => '2026-01-01',
            'enddate' => '2026-03-01',
            'rating' => 825,
            'related' => 'Related title',
            'similar' => 'Similar title',
            'anilist_id' => 456,
            'mal_id' => 789,
            'description' => 'Anime description',
            'creators' => 'Anime creator',
            'categories' => 'Action',
            'characters' => 'Main character',
        ];

        $html = view('admin.anidb.edit', [
            'anime' => $anime,
            'title' => 'AniDB Edit',
            'meta_title' => 'AniDB Edit',
        ])->render();

        $this->assertStringContainsString('Regression Test Anime', $html);
        $this->assertStringContainsString('value="123"', $html);
        $this->assertStringContainsString('Display: 8.3 / 10', $html);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function setGlobalViewData(?array $data): void
    {
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');

        $property->setValue(null, $data);
    }
}
