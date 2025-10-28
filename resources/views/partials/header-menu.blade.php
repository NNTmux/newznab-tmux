<nav class="bg-gray-800 dark:bg-gray-950">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Mobile menu button -->
            <button type="button" class="md:hidden text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white focus:outline-none" id="mobile-menu-toggle">
                <i class="fas fa-bars text-xl"></i>
            </button>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex md:items-center md:space-x-1 flex-1">
                @if(isset($parentcatlist))
                    @foreach($parentcatlist as $parentcat)
                        @if($parentcat['id'] == App\Models\Category::TV_ROOT)
                            <div class="relative dropdown-container">
                                <button class="dropdown-toggle flex items-center px-3 py-2 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-700 dark:hover:bg-gray-800 rounded transition">
                                    <i class="fa fa-television mr-2"></i>
                                    <span>{{ $parentcat['title'] }}</span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="dropdown-menu absolute left-0 top-full w-48 bg-gray-900 dark:bg-gray-950 rounded-md shadow-lg z-50">
                                    <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">All TV</a>
                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                    <a href="{{ route('series') }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">TV Series</a>
                                    <a href="{{ route('animelist') }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Anime Series</a>
                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                    @foreach($parentcat['categories'] as $subcat)
                                        <a href="{{ url('/browse/TV/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $subcat['title'] }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @elseif($parentcat['id'] == App\Models\Category::MOVIE_ROOT)
                            <div class="relative dropdown-container">
                                <button class="dropdown-toggle flex items-center px-3 py-2 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-700 dark:hover:bg-gray-800 rounded transition">
                                    <i class="fa fa-film mr-2"></i>
                                    <span>{{ $parentcat['title'] }}</span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="dropdown-menu absolute left-0 top-full w-48 bg-gray-900 dark:bg-gray-950 rounded-md shadow-lg z-50">
                                    @if(auth()->check() && auth()->user()->movieview == "1")
                                        <a href="{{ url('/' . $parentcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $parentcat['title'] }}</a>
                                    @else
                                        <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $parentcat['title'] }}</a>
                                    @endif
                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                    <a href="{{ route('mymovies') }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">My Movies</a>
                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                    @foreach($parentcat['categories'] as $subcat)
                                        @if(auth()->check() && auth()->user()->movieview == "1")
                                            <a href="{{ url('/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $subcat['title'] }}</a>
                                        @else
                                            <a href="{{ url('/browse/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $subcat['title'] }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @elseif($parentcat['id'] == App\Models\Category::GAME_ROOT)
                            <div class="relative dropdown-container">
                                <button class="dropdown-toggle flex items-center px-3 py-2 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-700 dark:hover:bg-gray-800 rounded transition">
                                    <i class="fa fa-gamepad mr-2"></i>
                                    <span>{{ $parentcat['title'] }}</span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="dropdown-menu absolute left-0 top-full w-48 bg-gray-900 dark:bg-gray-950 rounded-md shadow-lg z-50">
                                    @if(auth()->check() && auth()->user()->consoleview == "1")
                                        <a href="{{ url('/' . $parentcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $parentcat['title'] }}</a>
                                    @else
                                        <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $parentcat['title'] }}</a>
                                    @endif
                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                    @foreach($parentcat['categories'] as $subcat)
                                        @if(auth()->check() && auth()->user()->consoleview == "1")
                                            <a href="{{ url('/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $subcat['title'] }}</a>
                                        @else
                                            <a href="{{ url('/browse/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $subcat['title'] }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @elseif($parentcat['id'] == App\Models\Category::PC_ROOT)
                            <div class="relative dropdown-container">
                                <button class="dropdown-toggle flex items-center px-3 py-2 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-700 dark:hover:bg-gray-800 rounded transition">
                                    <i class="fa fa-desktop mr-2"></i>
                                    <span>{{ $parentcat['title'] }}</span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="dropdown-menu absolute left-0 top-full w-48 bg-gray-900 dark:bg-gray-950 rounded-md shadow-lg z-50">
                                    <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $parentcat['title'] }}</a>
                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                    @foreach($parentcat['categories'] as $subcat)
                                        @if(auth()->check() && auth()->user()->gameview == "1" && $subcat['id'] == App\Models\Category::PC_GAMES)
                                            <a href="{{ url('/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $subcat['title'] }}</a>
                                        @else
                                            <a href="{{ url('/browse/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $subcat['title'] }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="relative dropdown-container">
                                <button class="dropdown-toggle flex items-center px-3 py-2 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-700 dark:hover:bg-gray-800 rounded transition">
                                    <span>{{ $parentcat['title'] }}</span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="dropdown-menu absolute left-0 top-full w-48 bg-gray-900 dark:bg-gray-950 rounded-md shadow-lg z-50">
                                    <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $parentcat['title'] }}</a>
                                    @if(isset($parentcat['categories']) && count($parentcat['categories']) > 0)
                                        <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                        @foreach($parentcat['categories'] as $subcat)
                                            <a href="{{ url('/browse/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $subcat['title'] }}</a>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>

            <!-- Right side: Search and User Menu -->
            <div class="flex items-center space-x-2 md:space-x-4">
                <!-- Mobile Search Toggle -->
                <button type="button" class="lg:hidden text-gray-300 hover:text-white p-2 touch-target" id="mobile-search-toggle">
                    <i class="fa fa-search text-lg"></i>
                </button>

                <!-- Desktop Search Form -->
                <form method="GET" action="{{ route('search') }}" class="hidden lg:flex items-center">
                    <select name="t" class="bg-gray-700 text-white text-sm rounded-l px-3 py-2 border-r border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                    <input type="search" name="search" value="{{ $header_menu_search ?? '' }}" placeholder="Search..." class="bg-gray-700 text-white text-sm px-3 py-2 w-48 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit" class="bg-green-600 dark:bg-green-700 hover:bg-green-700 dark:hover:bg-green-800 text-white px-4 py-2 rounded-r transition">
                        <i class="fa fa-search"></i>
                    </button>
                </form>

                <!-- Mobile Search Form (Hidden by default) -->
                <div id="mobile-search-form" class="lg:hidden hidden absolute top-16 left-0 right-0 bg-gray-800 dark:bg-gray-950 p-4 shadow-lg z-50">
                    <form method="GET" action="{{ route('search') }}" class="space-y-3">
                        <select name="t" class="w-full bg-gray-700 text-white text-sm rounded px-3 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 touch-target">
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
                        <input type="search" name="search" value="{{ $header_menu_search ?? '' }}" placeholder="Search releases..." class="w-full bg-gray-700 text-white text-sm px-3 py-3 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 touch-target">
                        <button type="submit" class="w-full bg-green-600 dark:bg-green-700 hover:bg-green-700 dark:hover:bg-green-800 text-white px-4 py-3 rounded transition touch-target font-semibold">
                            <i class="fa fa-search mr-2"></i>Search
                        </button>
                    </form>
                </div>

                <!-- User Menu -->
                @auth
                    <div class="relative dropdown-container">
                        <button class="dropdown-toggle flex items-center space-x-2 text-gray-300 hover:text-white px-3 py-2 rounded hover:bg-gray-700 transition">
                            <span class="w-8 h-8 bg-blue-600 dark:bg-blue-700 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                {{ strtoupper(substr(auth()->user()->username, 0, 1)) }}
                            </span>
                            <span class="hidden md:block">{{ auth()->user()->username }}</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div class="dropdown-menu absolute right-0 top-full w-56 bg-gray-900 rounded-md shadow-lg z-50">
                            <a href="{{ url('/cart/index') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white">
                                <i class="fa fa-shopping-basket fa-fw mr-2"></i>My Download Basket
                            </a>
                            <a href="{{ route('mymovies') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white">
                                <i class="fa fa-film fa-fw mr-2"></i>My Movies
                            </a>
                            <a href="{{ route('myshows') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white">
                                <i class="fa fa-television fa-fw mr-2"></i>My Shows
                            </a>
                            <a href="{{ route('invitations.index') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white">
                                <i class="fa fa-envelope fa-fw mr-2"></i>My Invitations
                            </a>
                            <div class="border-t border-gray-700"></div>
                            <a href="{{ route('profileedit') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white">
                                <i class="fa fa-cog fa-fw mr-2"></i>Account Settings
                            </a>
                            @if(auth()->user()->hasRole('Admin'))
                                <a href="{{ url('/admin/index') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white">
                                    <i class="fa fa-cogs fa-fw mr-2"></i>Admin
                                </a>
                            @endif
                            <div class="border-t border-gray-700"></div>
                            <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white">
                                <i class="fa fa-user fa-fw mr-2"></i>Profile
                            </a>
                            <a href="{{ route('logout') }}" data-logout class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white">
                                <i class="fa fa-sign-out fa-fw mr-2"></i>Sign Out
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                                @csrf
                            </form>
                        </div>
                    </div>
                @else
                    <!-- Guest Links -->
                    <div class="flex items-center space-x-2">
                        <a href="{{ route('login') }}" class="px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700 rounded transition">
                            <i class="fa fa-sign-in mr-1"></i>Login
                        </a>
                        <a href="{{ route('register') }}" class="px-4 py-2 text-sm bg-green-600 dark:bg-green-700 hover:bg-green-700 dark:hover:bg-green-800 text-white rounded transition">
                            <i class="fa fa-user-plus mr-1"></i>Register
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</nav>


