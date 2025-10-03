<?php

namespace App\Http\Controllers;

use App\Events\UserLoggedIn;
use App\Models\Category;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BasePageController extends Controller
{
    public Settings $settings;

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
     */
    protected array $viewData = [];

    /**
     * BasePageController constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->middleware(['auth', 'web', '2fa'])->except('api', 'contact', 'showContactForm', 'callback', 'getNzb', 'terms', 'capabilities', 'movie', 'apiSearch', 'tv', 'details', 'failed', 'showRssDesc', 'fullFeedRss', 'categoryFeedRss', 'cartRss', 'myMoviesRss', 'myShowsRss', 'release', 'reset', 'showLinkRequestForm');
        // Buffer settings/DB connection.
        $this->settings = new Settings;

        // Initialize view data
        $this->viewData = [
            'serverroot' => url('/'),
            'site' => $this->settings,
        ];
    }

    public function paginate($query, $totalCount, $items, $page, $path, $reqQuery): LengthAwarePaginator
    {
        return new LengthAwarePaginator($query, $totalCount, $items, $page, ['path' => $path, 'query' => $reqQuery]);
    }

    /**
     * @throws \Exception
     */
    protected function setPreferences(): void
    {
        if (Auth::check()) {
            $this->userdata = User::find(Auth::id());
            $this->setUserPreferences();
        } else {
            $this->viewData = array_merge($this->viewData, [
                'isadmin' => false,
                'ismod' => false,
                'loggedin' => false,
            ]);
        }

        $this->viewData = array_merge($this->viewData, [
            'theme' => 'Gentele',
            'site' => $this->settings,
        ]);
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
     * @param  null  $message
     */
    public function show404($message = null): View
    {
        if ($message !== null) {
            return view('errors.404')->with('Message', $message);
        }

        return view('errors.404');
    }

    /**
     *  Set user preferences.
     */
    protected function setUserPreferences(): void
    {
        $this->userdata->categoryexclusions = User::getCategoryExclusionById(Auth::id());

        // Update last login every 15 mins.
        if (now()->subHours(3) > $this->userdata->lastlogin) {
            event(new UserLoggedIn($this->userdata));
        }

        $parentcatlist = Category::getForMenu($this->userdata->categoryexclusions);

        $this->viewData = array_merge($this->viewData, [
            'userdata' => $this->userdata,
            'loggedin' => true,
            'isadmin' => $this->userdata->hasRole('Admin'),
            'ismod' => $this->userdata->hasRole('Moderator'),
            'parentcatlist' => $parentcatlist,
            'header_menu_cat' => request()->input('t', ''),
        ]);
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
