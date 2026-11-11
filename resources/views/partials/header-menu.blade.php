@php
    $categoryMenu = \App\Support\Navigation\CategoryMenu::build($parentcatlist ?? [], auth()->user());
@endphp
<nav class="layout-primary-nav relative" aria-label="Content categories">
    <div class="container mx-auto max-w-[1600px] px-3 sm:px-5 lg:px-8">
        <div class="flex h-14 items-center justify-between gap-2">
            <!-- Category menu button (visible below lg) -->
            <button type="button" class="touch-target rounded-xl p-2 text-gray-300 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 lg:hidden" id="mobile-menu-toggle" aria-label="Toggle category navigation" aria-expanded="false" aria-controls="mobile-nav-panel">
                <i class="fas fa-layer-group text-lg" id="mobile-menu-icon-open" aria-hidden="true"></i>
                <i class="fas fa-times text-lg hidden" id="mobile-menu-icon-close" aria-hidden="true"></i>
            </button>

            <!-- Desktop category navigation: icons from lg, labels from xl -->
            <div class="layout-primary-nav__categories hidden min-w-0 flex-1 lg:flex lg:items-center lg:gap-0.5" id="desktop-nav">
                @foreach($categoryMenu as $entry)
                    @include('partials.header-menu.category-dropdown', ['entry' => $entry])
                @endforeach
            </div>

            <!-- Right side: Search and User Menu -->
            <div class="flex items-center space-x-2 lg:space-x-4 ml-auto">
                @auth
                    <!-- Download basket with live item count (updated by the Alpine cart store) -->
                    <a href="{{ url('/cart/index') }}" class="touch-target relative rounded-xl p-2 text-gray-300 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400">
                        <i class="fa-solid fa-shopping-basket text-lg" aria-hidden="true"></i>
                        <span class="sr-only">Download basket items:</span>
                        <span class="cart-count absolute -right-1 -top-1 min-w-5 rounded-full bg-accent px-1 text-center text-xs font-semibold leading-5 text-white {{ ($cartCount ?? 0) > 0 ? '' : 'hidden' }}">{{ $cartCount ?? 0 }}</span>
                    </a>
                @endauth
                <!-- Search toggle (visible below 2xl) -->
                <button type="button" class="touch-target rounded-xl p-2 text-gray-300 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 2xl:hidden" id="mobile-search-toggle" aria-label="Toggle release search" aria-expanded="false" aria-controls="mobile-search-form">
                    <i class="fa-solid fa-search text-lg" aria-hidden="true"></i>
                </button>

                <!-- Desktop Search Form with Autocomplete (visible at 2xl+) -->
                <form method="GET" action="{{ route('search') }}" class="layout-primary-nav__search relative hidden items-center gap-2 2xl:flex" id="header-search-form" role="search">
                    <label for="header-search-category" class="sr-only">Search category</label>
                    <select id="header-search-category" name="t" class="rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="-1">All</option>
                        @if(isset($parentcatlist))
                            @foreach($parentcatlist as $parentcat)
                                <option value="{{ $parentcat['id'] }}" {{ ($header_menu_cat ?? '') == $parentcat['id'] ? 'selected' : '' }} class="font-semibold">{{ $parentcat['title'] }}</option>
                                @foreach($parentcat['categories'] as $subcat)
                                    <option value="{{ $subcat['id'] }}" {{ ($header_menu_cat ?? '') == $subcat['id'] ? 'selected' : '' }}>&nbsp;&nbsp;{{ $subcat['title'] }}</option>
                                @endforeach
                            @endforeach
                        @endif
                    </select>
                    <div class="relative">
                        <label for="header-search-input" class="sr-only">Search releases</label>
                        <input type="search"
                               name="search"
                               id="header-search-input"
                               value="{{ $header_menu_search ?? '' }}"
                               placeholder="Search releases"
                               autocomplete="off"
                               class="w-40 rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-primary-500 xl:w-48">
                        <!-- Autocomplete dropdown for header -->
                        <div id="header-autocomplete-dropdown" class="hidden absolute z-50 w-64 mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto right-0">
                        </div>
                    </div>
                    <button type="submit" class="rounded-lg bg-accent px-4 py-2 text-white transition hover:bg-accent-hover dark:bg-accent-hover dark:hover:bg-primary-800" aria-label="Search releases">
                        <i class="fa-solid fa-search" aria-hidden="true"></i>
                    </button>
                </form>

                <!-- User Menu -->
                @auth
                    <div class="relative dropdown-container">
                        <button type="button" class="dropdown-toggle flex items-center space-x-2 text-gray-300 hover:text-white px-2 lg:px-3 py-2 rounded-lg hover:bg-white/10 transition">
                            <span class="w-8 h-8 bg-accent dark:bg-accent-hover rounded-full flex items-center justify-center text-white font-bold text-sm">
                                {{ strtoupper(substr(auth()->user()->username, 0, 1)) }}
                            </span>
                            <span class="hidden lg:block text-sm">{{ auth()->user()->username }}</span>
                            <i aria-hidden="true" class="fas fa-chevron-down text-xs hidden lg:inline"></i>
                        </button>
                        <div class="dropdown-menu absolute right-0 top-full z-50 mt-1 w-60 rounded-xl bg-gray-900 p-1.5 shadow-overlay ring-1 ring-white/10 dark:bg-gray-950">
                            <a href="{{ url('/cart/index') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white">
                                <i aria-hidden="true" class="fa-solid fa-shopping-basket fa-fw mr-2"></i>My Download Basket
                            </a>
                            <a href="{{ route('mymovies') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white">
                                <i aria-hidden="true" class="fa-solid fa-film fa-fw mr-2"></i>My Movies
                            </a>
                            <a href="{{ route('myshows') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white">
                                <i aria-hidden="true" class="fa-solid fa-television fa-fw mr-2"></i>My Shows
                            </a>
                            <a href="{{ route('invitations.index') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white">
                                <i aria-hidden="true" class="fa-solid fa-envelope fa-fw mr-2"></i>My Invitations
                            </a>
                            <div class="my-1.5 border-t border-white/10"></div>
                            <a href="{{ route('profileedit') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white">
                                <i aria-hidden="true" class="fa-solid fa-cog fa-fw mr-2"></i>Account Settings
                            </a>
                            @if(auth()->user()->hasRole('Admin'))
                                <a href="{{ url('/admin/index') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white">
                                    <i aria-hidden="true" class="fa-solid fa-cogs fa-fw mr-2"></i>Admin
                                </a>
                            @endif
                            <div class="my-1.5 border-t border-white/10"></div>
                            <!-- Theme Switcher -->
                            <div class="px-3 py-2">
                                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-400">Theme</span>
                                @include('partials.theme-switcher', ['switcherId' => 'dropdown-theme-switcher', 'btnClass' => 'dropdown-theme-btn', 'mobile' => false])
                            </div>
                            <!-- Color Scheme -->
                            <div class="px-3 py-2">
                                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-400">Color Scheme</span>
                                @include('partials.scheme-switcher', ['switcherId' => 'dropdown-scheme-switcher', 'btnClass' => 'dropdown-scheme-btn', 'mobile' => false])
                            </div>
                            <div class="my-1.5 border-t border-white/10"></div>
                            <a href="{{ route('profile') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white">
                                <i aria-hidden="true" class="fa-solid fa-user fa-fw mr-2"></i>Profile
                            </a>
                            <a href="{{ route('logout') }}" data-logout class="block rounded-lg px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 hover:text-white">
                                <i aria-hidden="true" class="fas fa-sign-out-alt fa-fw mr-2"></i>Sign Out
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                                @csrf
                            </form>
                        </div>
                    </div>
                @else
                    <!-- Guest Links -->
                    <div class="flex items-center space-x-2">
                        <a href="{{ route('login') }}" class="px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-white/10 rounded-lg transition">
                            <i aria-hidden="true" class="fa-solid fa-sign-in sm:mr-1"></i><span class="sr-only sm:not-sr-only">Login</span>
                        </a>
                        <a href="{{ route('register') }}" class="px-3 py-2 text-sm bg-accent dark:bg-accent-hover hover:bg-accent-hover dark:hover:bg-primary-800 text-white rounded-lg transition">
                            <i aria-hidden="true" class="fa-solid fa-user-plus sm:mr-1"></i><span class="sr-only sm:not-sr-only">Register</span>
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </div>

    <!-- Mobile Search Form (slides down below header, visible below 2xl) -->
    <div id="mobile-search-form" class="2xl:hidden hidden bg-gray-800 dark:bg-gray-950 border-t border-white/10 dark:border-white/5 shadow-lg z-50">
        <div class="container mx-auto px-4 py-3">
            <form method="GET" action="{{ route('search') }}" class="space-y-3" id="mobile-search-form-el">
                <label for="mobile-search-category" class="sr-only">Search category</label>
                <select id="mobile-search-category" name="t" class="w-full bg-gray-700 text-white text-sm rounded-lg px-3 py-3 focus:outline-none focus:ring-2 focus:ring-primary-500 touch-target">
                    <option value="-1">All Categories</option>
                    @if(isset($parentcatlist))
                        @foreach($parentcatlist as $parentcat)
                            <option value="{{ $parentcat['id'] }}" {{ ($header_menu_cat ?? '') == $parentcat['id'] ? 'selected' : '' }} class="font-semibold">{{ $parentcat['title'] }}</option>
                            @foreach($parentcat['categories'] as $subcat)
                                <option value="{{ $subcat['id'] }}" {{ ($header_menu_cat ?? '') == $subcat['id'] ? 'selected' : '' }}>&nbsp;&nbsp;{{ $subcat['title'] }}</option>
                            @endforeach
                        @endforeach
                    @endif
                </select>
                <div class="relative">
                    <label for="mobile-search-input" class="sr-only">Search releases</label>
                    <input type="search"
                           name="search"
                           id="mobile-search-input"
                           value="{{ $header_menu_search ?? '' }}"
                           placeholder="Search releases..."
                           autocomplete="off"
                           class="w-full bg-gray-700 text-white text-sm px-3 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 touch-target">
                    <!-- Autocomplete dropdown for mobile -->
                    <div id="mobile-autocomplete-dropdown" class="hidden absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    </div>
                </div>
                <button type="submit" class="w-full bg-accent dark:bg-accent-hover hover:bg-accent-hover dark:hover:bg-primary-800 text-white px-4 py-3 rounded-lg transition touch-target font-semibold">
                    <i aria-hidden="true" class="fa-solid fa-search mr-2"></i>Search
                </button>
            </form>
        </div>
    </div>

    <!-- Mobile Navigation Panel (slides down below header, visible below lg) -->
    <div id="mobile-nav-panel" class="lg:hidden hidden bg-gray-800 dark:bg-gray-950 border-t border-white/10 dark:border-white/5 shadow-lg z-50 max-h-[70vh] overflow-y-auto overscroll-contain">
        <div class="container mx-auto px-4 py-3">
            <div class="space-y-1">
                @foreach($categoryMenu as $entry)
                    <div class="mobile-nav-section">
                        <button type="button" class="mobile-nav-toggle w-full flex items-center justify-between px-4 py-3 text-gray-200 hover:bg-white/10 dark:hover:bg-white/5 rounded-lg transition touch-target">
                            <span class="flex items-center">
                                <i aria-hidden="true" class="fa-solid {{ $entry['icon'] }} fa-fw mr-3 text-gray-400"></i>
                                <span class="font-medium">{{ $entry['title'] }}</span>
                            </span>
                            <i aria-hidden="true" class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200 mobile-nav-chevron"></i>
                        </button>
                        <div class="mobile-nav-submenu hidden ml-4 mt-1 space-y-0.5 pb-1">
                            @foreach($entry['links'] as $link)
                                <a href="{{ $link['url'] }}" class="block px-4 py-2.5 text-sm font-medium text-gray-200 hover:bg-white/10 dark:hover:bg-white/5 hover:text-white rounded-lg transition touch-target">{{ $link['label'] }}</a>
                            @endforeach
                            @foreach($entry['children'] as $child)
                                <a href="{{ $child['url'] }}" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-white/10 dark:hover:bg-white/5 hover:text-white rounded-lg transition touch-target">{{ $child['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Mobile User Quick Links -->
            @auth
                <div class="border-t border-white/10 dark:border-white/5 mt-3 pt-3 space-y-0.5">
                    <a href="{{ url('/cart/index') }}" class="flex items-center px-4 py-2.5 text-sm text-gray-300 hover:bg-white/10 dark:hover:bg-white/5 hover:text-white rounded-lg transition touch-target">
                        <i aria-hidden="true" class="fa-solid fa-shopping-basket fa-fw mr-3 text-gray-400"></i>My Download Basket
                    </a>
                    <a href="{{ route('profileedit') }}" class="flex items-center px-4 py-2.5 text-sm text-gray-300 hover:bg-white/10 dark:hover:bg-white/5 hover:text-white rounded-lg transition touch-target">
                        <i aria-hidden="true" class="fa-solid fa-cog fa-fw mr-3 text-gray-400"></i>Account Settings
                    </a>
                    @if(auth()->user()->hasRole('Admin'))
                        <a href="{{ url('/admin/index') }}" class="flex items-center px-4 py-2.5 text-sm text-gray-300 hover:bg-white/10 dark:hover:bg-white/5 hover:text-white rounded-lg transition touch-target">
                            <i aria-hidden="true" class="fa-solid fa-cogs fa-fw mr-3 text-gray-400"></i>Admin
                        </a>
                    @endif
                    <!-- Mobile Theme Switcher -->
                    <div class="px-4 py-2.5">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-wider text-gray-400">Theme</span>
                        @include('partials.theme-switcher', ['switcherId' => 'mobile-theme-switcher', 'btnClass' => 'mobile-theme-btn', 'mobile' => true])
                    </div>
                    <!-- Mobile Color Scheme -->
                    <div class="px-4 py-2.5">
                        <span class="mb-2 block text-xs font-semibold uppercase tracking-wider text-gray-400">Color Scheme</span>
                        @include('partials.scheme-switcher', ['switcherId' => 'mobile-scheme-switcher', 'btnClass' => 'mobile-scheme-btn', 'mobile' => true])
                    </div>
                </div>
            @endauth
        </div>
    </div>
</nav>
