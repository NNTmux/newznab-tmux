<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class NfoModalViewTest extends TestCase
{
    public function test_modal_view_returns_only_the_escaped_nfo_text_without_the_site_layout(): void
    {
        $view = $this->view('nfo.modal', ['nfo' => ['nfoUTF' => 'Scene <b>notes</b>']]);

        $view->assertSee('<pre class="nfo-content">Scene &lt;b&gt;notes&lt;/b&gt;</pre>', false)
            ->assertDontSee('<html', false)
            ->assertDontSee('<nav', false);
    }

    public function test_modal_view_falls_back_when_no_nfo_text_is_available(): void
    {
        $this->view('nfo.modal', ['nfo' => []])
            ->assertSee('NFO content not available');
    }
}
