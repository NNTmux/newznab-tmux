<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class BasePageController extends Controller
{
    /**
     * @var Collection<int, mixed>
     */
    public \Illuminate\Support\Collection $settings; // @phpstan-ignore property.phpDocType, class.notFound, missingType.generics

    public string $title = '';

    public string $content = '';

    public string $meta_keywords = '';

    public string $meta_title = '';

    public string $meta_description = '';

    /**
     * Current page the user is browsing. ie browse.
     */
    public string $page = '';

    public User $userdata;

    /**
     * User's theme.
     */
    protected string $theme = 'Gentele';

    /**
     * View data array for Blade templates
     *
     * @var array<string, mixed>
     */
    protected array $viewData = [];

    /**
     * BasePageController constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->middleware(['auth', 'web', '2fa'])->except('api', 'contact', 'showContactForm', 'callback', 'btcPayCallback', 'getNzb', 'terms', 'privacyPolicy', 'capabilities', 'movie', 'apiSearch', 'tv', 'details', 'failed', 'showRssDesc', 'fullFeedRss', 'categoryFeedRss', 'cartRss', 'myMoviesRss', 'myShowsRss', 'trendingMoviesRss', 'trendingShowsRss', 'release', 'reset', 'showLinkRequestForm');

        // Load settings as collection with caching (5 minutes)
        $this->settings = Cache::remember('site_settings', 300, function () {
            return Settings::query()->pluck('value', 'name');
        });

        // Initialize view data FIRST with serverroot
        $this->viewData = [
            'serverroot' => url('/'),
        ];

        // Then add the converted settings array as 'site' with caching
        $this->viewData['site'] = Cache::remember('site_settings_converted', 300, function () {
            return $this->settings->map(function ($value) {
                return Settings::convertValue($value);
            })->all();
        });

        // Initialize userdata property for controllers that need it
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $userId = Auth::id();
                $this->userdata = User::find($userId);
                // Cache category exclusions per user (5 minutes)
                $this->userdata->categoryexclusions = Cache::remember(
                    'user_category_exclusions_'.$userId,
                    300,
                    fn () => User::getCategoryExclusionById($userId)
                );
            }

            return $next($request);
        });
    }

    /**
     * @return LengthAwarePaginator<int, mixed>
     */
    public function paginate(mixed $query, mixed $totalCount, mixed $items, mixed $page, mixed $path, mixed $reqQuery): LengthAwarePaginator
    {
        return new LengthAwarePaginator($query, $totalCount, $items, $page, ['path' => $path, 'query' => $reqQuery]);
    }

    /**
     * Check if request is POST.
     */
    public function isPostBack(Request $request): bool
    {
        return $request->isMethod('POST');
    }

    /**
     * Show 404 page.
     *
     * @param  string|null  $message
     */
    public function show404($message = null): View
    {
        if ($message !== null) {
            return view('errors.404')->with('Message', $message);
        }

        return view('errors.404');
    }

    /**
     *  Set admin preferences.
     */
    public function setAdminPrefs(): void
    {
        $this->viewData['catClass'] = Category::class;
    }

    /**
     * Output the page using Blade views.
     */
    public function pagerender(): View
    {
        return view('layouts.main', $this->viewData);
    }

    /**
     * Output a page using the admin template.
     *
     * @throws \Exception
     */
    public function adminrender(): View
    {
        return view('layouts.admin', $this->viewData);
    }

    /**
     * @throws \Exception
     */
    public function adminBasePage(): View
    {
        $this->setAdminPrefs();
        $this->viewData = array_merge($this->viewData, [
            'meta_title' => 'Admin Home',
            'meta_description' => 'Admin home page',
        ]);

        return $this->adminrender();
    }
}
