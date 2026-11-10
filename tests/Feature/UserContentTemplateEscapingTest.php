<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserContentTemplateEscapingTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function forumPendingApprovalViews(): array
    {
        return [
            'pending posts' => ['forum/blade-tailwind/views/post/pending-approval.blade.php'],
            'pending threads' => ['forum/blade-tailwind/views/thread/pending-approval.blade.php'],
        ];
    }

    #[Test]
    #[DataProvider('forumPendingApprovalViews')]
    public function forum_pending_approval_views_never_print_raw_post_content(string $view): void
    {
        $template = (string) file_get_contents(resource_path($view));

        $this->assertDoesNotMatchRegularExpression('/\{!!\s*\$[\w\->]*content\s*!!\}/', $template);
        $this->assertStringContainsString('Forum::render(', $template);
    }

    #[Test]
    public function forum_alerts_escape_their_message(): void
    {
        $html = view('forum::partials.alert', ['type' => 'success', 'message' => 'Marked <img src=x onerror=alert(1)> as read'])->render();

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    #[Test]
    public function forum_private_ancestor_link_escapes_the_category_title(): void
    {
        $template = (string) file_get_contents(resource_path('forum/blade-tailwind/views/category/modals/edit.blade.php'));

        $this->assertStringContainsString('e($privateAncestor->title)', $template);
        $this->assertStringContainsString('e($privateAncestor->route)', $template);
    }

    #[Test]
    public function movie_page_trailer_is_an_escaped_new_tab_link(): void
    {
        $block = $this->trailerBlock();
        $site = (object) ['dereferrerLink' => ''];

        $html = Blade::render($block, ['site' => $site, 'movie' => ['trailer_url' => 'https://www.youtube.com/watch?v=x"><script>alert(1)</script>']]);
        $withoutTrailer = Blade::render($block, ['site' => $site, 'movie' => ['trailer_url' => null]]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('href="https://www.youtube.com/watch?v=x&quot;&gt;&lt;script&gt;', $html);
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringNotContainsString('Watch Trailer', $withoutTrailer);
    }

    private function trailerBlock(): string
    {
        $page = (string) file_get_contents(resource_path('views/movies/viewmoviefull.blade.php'));
        $start = strpos($page, '<!-- Trailer -->');
        $this->assertNotFalse($start);
        $end = strpos($page, '@endif', $start);
        $this->assertNotFalse($end);

        return substr($page, $start, $end - $start + \strlen('@endif'));
    }
}
