<nav class="flex flex-col space-y-1 p-4">
    <!-- Browse Menu -->
    <div class="sidebar-section">
        <button class="sidebar-toggle w-full flex items-center justify-between px-4 py-3 text-white hover:bg-gray-800 rounded transition" data-target="submenu1">
            <div class="flex items-center">
                <i class="fa fa-dashboard fa-fw mr-3"></i>
                <span>Browse</span>
            </div>
            <i class="fas fa-chevron-down text-xs transition-transform"></i>
        </button>
        <div id="submenu1" class="sidebar-submenu ml-4 mt-1 space-y-1 hidden">
            @if(auth()->check() && auth()->user()->consoleview && auth()->user()->can('view console'))
                <a href="{{ route('Console') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-gamepad fa-fw mr-2"></i>
                    <span>Console</span>
                </a>
            @endif
            @if(auth()->check() && auth()->user()->movieview && auth()->user()->can('view movies'))
                <a href="{{ route('Movies') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-film fa-fw mr-2"></i>
                    <span>Movies</span>
                </a>
                <a href="{{ route('trending-movies') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-fire fa-fw mr-2"></i>
                    <span>Trending Movies</span>
                </a>
            @endif
            @if(auth()->check() && auth()->user()->musicview && auth()->user()->can('view audio'))
                <a href="{{ route('Audio') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-music fa-fw mr-2"></i>
                    <span>Audio</span>
                </a>
            @endif
            @if(auth()->check() && auth()->user()->gameview && auth()->user()->can('view pc'))
                <a href="{{ route('Games') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-gamepad fa-fw mr-2"></i>
                    <span>Games</span>
                </a>
            @endif
            @if(auth()->check() && auth()->user()->can('view tv'))
                <a href="{{ route('series') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-television fa-fw mr-2"></i>
                    <span>TV</span>
                </a>
                <a href="{{ route('trending-tv') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-fire fa-fw mr-2"></i>
                    <span>Trending TV</span>
                </a>
            @endif
            @if(auth()->check() && auth()->user()->xxxview && auth()->user()->can('view adult'))
                <a href="{{ route('XXX') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-venus-mars fa-fw mr-2"></i>
                    <span>Adult</span>
                </a>
            @endif
            @if(auth()->check() && auth()->user()->bookview && auth()->user()->can('view books'))
                <a href="{{ route('Books') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-book-open fa-fw mr-2"></i>
                    <span>Books</span>
                </a>
            @endif
            <a href="{{ url('browse/All') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-list-ul fa-fw mr-2"></i>
                <span>Browse All Releases</span>
            </a>
            <a href="{{ route('browsegroup') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-layer-group fa-fw mr-2"></i>
                <span>Browse Groups</span>
            </a>
        </div>
    </div>

    <!-- Articles & Links Menu -->
    <div class="sidebar-section">
        <button class="sidebar-toggle w-full flex items-center justify-between px-4 py-3 text-white hover:bg-gray-800 rounded transition" data-target="submenu2">
            <div class="flex items-center">
                <i class="fa fa-edit fa-fw mr-3"></i>
                <span>Articles & Links</span>
            </div>
            <i class="fas fa-chevron-down text-xs transition-transform"></i>
        </button>
        <div id="submenu2" class="sidebar-submenu ml-4 mt-1 space-y-1 hidden">
            <a href="{{ url('forum') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fab fa-forumbee fa-fw mr-2"></i>
                <span>Forum</span>
            </a>
            <a href="{{ route('search') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fab fa-searchengin fa-fw mr-2"></i>
                <span>Search</span>
            </a>
            <a href="{{ url('search?search_type=adv') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fab fa-searchengin fa-fw mr-2"></i>
                <span>Advanced Search</span>
            </a>
            <a href="{{ route('rsshelp') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-rss-square fa-fw mr-2"></i>
                <span>RSS Feeds</span>
            </a>
            <a href="{{ route('apihelp') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-hands-helping fa-fw mr-2"></i>
                <span>API</span>
            </a>
            <a href="{{ route('apiv2help') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-hands-helping fa-fw mr-2"></i>
                <span>API V2</span>
            </a>
            <a href="{{ route('contact-us') }}" class="flex items-center px-4 py-2 text-gray-300 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-envelope fa-fw mr-2"></i>
                <span>Contact Us</span>
            </a>
        </div>
    </div>

    <!-- Extend/Upgrade Account -->
    @auth
        @php
            $userRole = auth()->user()->roles->first()?->name ?? 'user';
        @endphp
        @if($userRole !== 'Admin')
            @if($userRole === 'User')
                <a href="https://simplegate.space/apps/3MjgKvosMZtc2sSxiRBwadDCn1zA/pos" target="_blank" class="flex items-center px-4 py-3 text-white hover:bg-gray-800 rounded transition mt-4">
                    <i class="fa fa-arrow-up fa-fw mr-3"></i>
                    <span>Upgrade Your Account</span>
                </a>
            @else
                <a href="https://simplegate.space/apps/3MjgKvosMZtc2sSxiRBwadDCn1zA/pos" target="_blank" class="flex items-center px-4 py-3 text-white hover:bg-gray-800 rounded transition mt-4">
                    <i class="fa fa-clock fa-fw mr-3"></i>
                    <span>Extend Your Account</span>
                </a>
            @endif
        @endif
    @endauth

    <!-- Sign Out -->
    @auth
        <a href="{{ route('logout') }}" data-logout class="flex items-center px-4 py-3 text-white hover:bg-gray-800 rounded transition mt-4">
            <i class="fa fa-sign-out-alt fa-fw mr-3"></i>
            <span>Sign Out</span>
        </a>
        <form id="sidebar-logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
            @csrf
        </form>
    @endauth
</nav>



