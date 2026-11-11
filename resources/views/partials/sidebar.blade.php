@php
    $sidebarUser = auth()->user();
    $currentPath = '/'.trim(request()->path(), '/');
    $isCurrent = static fn (string $url): bool => rtrim((string) (parse_url($url, PHP_URL_PATH) ?: '/'), '/') === rtrim($currentPath, '/')
        && (parse_url($url, PHP_URL_QUERY) === null || str_contains((string) request()->getQueryString(), (string) parse_url($url, PHP_URL_QUERY)));

    $sidebarSections = [
        [
            'label' => __('Browse'),
            'links' => array_values(array_filter([
                $sidebarUser?->consoleview && $sidebarUser->can('view console') ? ['label' => __('Console'), 'url' => route('Console'), 'icon' => 'fa-solid fa-gamepad'] : null,
                $sidebarUser?->movieview && $sidebarUser->can('view movies') ? ['label' => __('Movies'), 'url' => route('Movies'), 'icon' => 'fa-solid fa-film'] : null,
                $sidebarUser?->movieview && $sidebarUser->can('view movies') ? ['label' => __('Trending Movies'), 'url' => route('trending-movies'), 'icon' => 'fa-solid fa-fire'] : null,
                $sidebarUser?->musicview && $sidebarUser->can('view audio') ? ['label' => __('Audio'), 'url' => route('Audio'), 'icon' => 'fa-solid fa-music'] : null,
                $sidebarUser?->gameview && $sidebarUser->can('view pc') ? ['label' => __('Games'), 'url' => route('Games'), 'icon' => 'fa-solid fa-gamepad'] : null,
                $sidebarUser?->can('view tv') ? ['label' => __('TV'), 'url' => route('series'), 'icon' => 'fa-solid fa-television'] : null,
                $sidebarUser?->can('view tv') ? ['label' => __('Trending TV'), 'url' => route('trending-tv'), 'icon' => 'fa-solid fa-fire'] : null,
                $sidebarUser?->xxxview && $sidebarUser->can('view adult') ? ['label' => __('Adult'), 'url' => route('XXX'), 'icon' => 'fa-solid fa-venus-mars'] : null,
                $sidebarUser?->bookview && $sidebarUser->can('view books') ? ['label' => __('Books'), 'url' => route('Books'), 'icon' => 'fa-solid fa-book-open'] : null,
                ['label' => __('All Releases'), 'url' => url('browse/All'), 'icon' => 'fa-solid fa-list-ul'],
                ['label' => __('Groups'), 'url' => route('browsegroup'), 'icon' => 'fa-solid fa-layer-group'],
            ])),
        ],
        [
            'label' => __('Search & tools'),
            'links' => [
                ['label' => __('Search'), 'url' => route('search'), 'icon' => 'fa-solid fa-magnifying-glass'],
                ['label' => __('Advanced Search'), 'url' => url('search?search_type=adv'), 'icon' => 'fa-solid fa-sliders'],
                ['label' => __('RSS Feeds'), 'url' => route('rsshelp'), 'icon' => 'fa-solid fa-rss'],
                ['label' => __('API'), 'url' => route('apihelp'), 'icon' => 'fa-solid fa-code'],
                ['label' => __('API V2'), 'url' => route('apiv2help'), 'icon' => 'fa-solid fa-code'],
            ],
        ],
        [
            'label' => __('Community'),
            'links' => [
                ['label' => __('Forum'), 'url' => url('forum'), 'icon' => 'fab fa-forumbee'],
                ['label' => __('Site status'), 'url' => route('status'), 'icon' => 'fa-solid fa-signal'],
                ['label' => __('Contact Us'), 'url' => route('contact-us'), 'icon' => 'fa-solid fa-envelope'],
            ],
        ],
    ];
@endphp

<div class="user-sidebar-menu flex flex-col gap-5 px-3 py-2">
    @foreach($sidebarSections as $section)
        <section aria-labelledby="sidebar-section-{{ $loop->index }}">
            <h2 id="sidebar-section-{{ $loop->index }}" class="sidebar-section__label px-3 pb-1.5 text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-white/65">{{ $section['label'] }}</h2>
            <div class="flex flex-col gap-0.5">
                @foreach($section['links'] as $link)
                    <x-nav-link :href="$link['url']" :icon="$link['icon']" :active="$isCurrent($link['url'])">{{ $link['label'] }}</x-nav-link>
                @endforeach
            </div>
        </section>
    @endforeach

    @if(isset($usefulLinks) && $usefulLinks->isNotEmpty())
        <section class="sidebar-section">
            <button type="button" class="sidebar-toggle nav-link w-full justify-between" data-target="submenu-useful-links">
                <span class="flex items-center gap-3">
                    <i aria-hidden="true" class="fa-solid fa-link fa-fw"></i>
                    <span>{{ __('Useful Links') }}</span>
                </span>
                <i aria-hidden="true" class="fas fa-chevron-down text-xs transition-transform"></i>
            </button>
            <div id="submenu-useful-links" class="sidebar-submenu ml-4 mt-1 space-y-3 hidden">
                @foreach($usefulLinks as $link)
                    <div class="px-2 py-2">
                        @if($link->url)
                            <a href="{{ $link->resolved_url }}"
                               class="block text-white/75 transition hover:text-white"
                               @if($link->is_external_url) target="_blank" rel="noopener noreferrer" @endif>
                                <div class="mb-1 font-medium">{{ $link->title }}</div>
                                @if($link->body)
                                    <div class="useful-link-content text-sm text-white/65">
                                        {!! html_entity_decode(trim($link->body, '\'"')) !!}
                                    </div>
                                @endif
                            </a>
                        @else
                            <div class="text-white/75">
                                <div class="mb-1 font-medium">{{ $link->title }}</div>
                                @if($link->body)
                                    <div class="useful-link-content text-sm text-white/65">
                                        {!! html_entity_decode(trim($link->body, '\'"')) !!}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @auth
        <div class="border-t border-white/10 pt-3">
            @php
                $userRole = auth()->user()->roles->first()?->name ?? 'user';
            @endphp
            {{-- Payment placeholders (see the btcpay route); replace the URL once payments are configured. --}}
            @if($userRole !== 'Admin')
                @if($userRole === 'User')
                    <a href="https://example.com" target="_blank" rel="noopener noreferrer" class="nav-link">
                        <i aria-hidden="true" class="fa-solid fa-arrow-up fa-fw shrink-0"></i>
                        <span>Upgrade Your Account</span>
                    </a>
                @else
                    <a href="https://example.com" target="_blank" rel="noopener noreferrer" class="nav-link">
                        <i aria-hidden="true" class="fa-solid fa-clock fa-fw shrink-0"></i>
                        <span>Extend Your Account</span>
                    </a>
                @endif
            @endif
            <a href="{{ route('logout') }}" data-logout class="nav-link">
                <i aria-hidden="true" class="fa-solid fa-right-from-bracket fa-fw shrink-0"></i>
                <span>{{ __('Sign Out') }}</span>
            </a>
            <form id="sidebar-logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                @csrf
            </form>
        </div>
    @endauth
</div>
