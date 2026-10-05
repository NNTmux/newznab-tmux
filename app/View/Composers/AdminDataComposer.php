<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\User;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\SiteLogoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminDataComposer
{
    public function __construct(
        private readonly SiteLogoService $siteLogoService,
        private readonly ConfigurationProvider $configuration,
    ) {}

    /**
     * Bind lightweight admin data to the view.
     */
    public function compose(View $view): void
    {
        $user = Auth::user();
        $isNntmuxUser = $user instanceof User;
        $site = $this->configuration->site();

        $view->with([
            'serverroot' => url('/'),
            'site' => $site,
            'siteLogoUrl' => $this->siteLogoService->url($site->logoPath),
            'userdata' => $user,
            'loggedin' => $user !== null,
            'isadmin' => $isNntmuxUser && $user->hasRole('Admin'),
            'ismod' => $isNntmuxUser && $user->hasRole('Moderator'),
            'userTheme' => $isNntmuxUser ? ($user->theme_preference ?? 'light') : 'light',
            'userColorScheme' => $isNntmuxUser ? ($user->color_scheme ?? 'blue') : 'blue',
        ]);
    }
}
