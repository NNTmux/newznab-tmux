<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminTmuxController;
use App\Services\Tmux\Tmux;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminTmuxControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });
        Cache::flush();
    }

    public function test_saving_enabled_postprocessing_creates_missing_switches_read_by_monitor(): void
    {
        $request = Request::create('/admin/tmux-edit', 'POST', [
            'action' => 'submit', 'post_non' => '1', 'post_amazon' => '1', '_token' => 'ignored',
        ]);

        $response = (new AdminTmuxController)->edit($request);

        $settings = (new Tmux)->getMonitorSettings();
        $this->assertSame(1, $settings['post_non']);
        $this->assertSame(1, $settings['post_amazon']);
        $this->assertTrue($response->isRedirect());
        $this->assertFalse(DB::table('settings')->where('name', '_token')->exists());
    }

    public function test_existing_switches_can_be_disabled_and_cached_page_reloads_saved_values(): void
    {
        DB::table('settings')->insert([
            ['name' => 'post_non', 'value' => '1'], ['name' => 'post_amazon', 'value' => '1'],
        ]);
        (new AdminTmuxController)->edit(Request::create('/admin/tmux-edit', 'GET'));

        (new AdminTmuxController)->edit(Request::create('/admin/tmux-edit', 'POST', [
            'action' => 'submit', 'post_non' => '0', 'post_amazon' => '0',
        ]));
        $view = (new AdminTmuxController)->edit(Request::create('/admin/tmux-edit', 'GET'));

        $this->assertSame(0, $view->getData()['site']['post_non']);
        $this->assertSame(0, $view->getData()['site']['post_amazon']);
        $settings = (new Tmux)->getMonitorSettings();
        $this->assertSame(0, $settings['post_non']);
        $this->assertSame(0, $settings['post_amazon']);
    }

    public function test_missing_switches_render_as_disabled_instead_of_browser_default_yes(): void
    {
        $view = (new AdminTmuxController)->edit(Request::create('/admin/tmux-edit', 'GET'));
        $html = view('admin.site.tmux-edit-content', $view->getData())->render();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);

        foreach (['post_non', 'post_amazon'] as $name) {
            $selected = $xpath->query('//select[@name="'.$name.'"]/option[@selected]');
            $this->assertNotFalse($selected);
            $this->assertSame(1, $selected->length);
            $this->assertSame('0', $selected->item(0)?->attributes?->getNamedItem('value')?->nodeValue);
        }
    }

    public function test_invalid_postprocessing_switches_are_rejected_before_saving(): void
    {
        $this->expectException(ValidationException::class);

        (new AdminTmuxController)->edit(Request::create('/admin/tmux-edit', 'POST', [
            'action' => 'submit', 'post_non' => 'unexpected', 'post_amazon' => ['1'],
        ]));
    }

    public function test_get_cannot_create_postprocessing_settings(): void
    {
        (new AdminTmuxController)->edit(Request::create('/admin/tmux-edit', 'GET', [
            'action' => 'submit', 'post_non' => '1', 'post_amazon' => '1',
        ]));

        $this->assertSame(0, DB::table('settings')->count());
    }
}
