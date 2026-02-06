<nav class="bg-gray-800 dark:bg-gray-950 relative">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Mobile menu button (visible below lg) -->
            <button type="button" class="lg:hidden text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white focus:outline-none p-2 touch-target" id="mobile-menu-toggle" aria-expanded="false" aria-controls="mobile-nav-panel">
                <i class="fas fa-bars text-xl" id="mobile-menu-icon-open"></i>
                <i class="fas fa-times text-xl hidden" id="mobile-menu-icon-close"></i>
            </button>

            <!-- Desktop Navigation (visible at lg+) -->
            <div class="hidden lg:flex lg:items-center lg:space-x-1 flex-1 min-w-0" id="desktop-nav">
                @if(isset($parentcatlist))
                    @foreach($parentcatlist as $parentcat)
                        @if($parentcat['id'] == App\Models\Category::TV_ROOT)
                            <div class="relative dropdown-container flex-shrink-0">
                                <button class="dropdown-toggle flex items-center px-3 py-2 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-700 dark:hover:bg-gray-800 rounded-lg transition text-sm whitespace-nowrap">
                                    <i class="fa fa-television mr-1.5"></i>
                                    <span>{{ $parentcat['title'] }}</span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="dropdown-menu absolute left-0 top-full w-48 bg-gray-900 dark:bg-gray-950 rounded-xl shadow-lg z-50">
                                    <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">All TV</a>
                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                    <a href="{{ route('series') }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">TV Series</a>
                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                    @foreach($parentcat['categories'] as $subcat)
                                        @if($subcat['id'] == App\Models\Category::TV_FOREIGN)
                                            <div class="relative submenu-container">
                                                <a href="{{ url('/browse/TV/' . $subcat['title']) }}" class="submenu-toggle flex items-center justify-between px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">
                                                    <span>{{ $subcat['title'] }}</span>
                                                    <i class="fas fa-chevron-right text-xs"></i>
                                                </a>
                                                <div class="submenu absolute left-full top-0 w-40 bg-gray-900 dark:bg-gray-950 rounded-xl shadow-lg z-50 ml-0.5" style="display: none;">
                                                    <a href="{{ url('/browse/TV/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">All Foreign</a>
                                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                                    <a href="{{ route('search', ['search' => 'German|Deutsch|DE|GER', 't' => App\Models\Category::TV_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">German</a>
                                                    <a href="{{ route('search', ['search' => 'French|Francais|FR|FRENCH|VOSTFR|TRUEFRENCH', 't' => App\Models\Category::TV_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">French</a>
                                                    <a href="{{ route('search', ['search' => 'Spanish|Espanol|ES|ESP|SPANISH|Latino', 't' => App\Models\Category::TV_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Spanish</a>
                                                    <a href="{{ route('search', ['search' => 'Italian|Italiano|IT|ITA', 't' => App\Models\Category::TV_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Italian</a>
                                                    <a href="{{ route('search', ['search' => 'Dutch|NL|Flemish|Nederlands|DUTCH', 't' => App\Models\Category::TV_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Dutch</a>
                                                    <a href="{{ route('search', ['search' => 'Japanese|JP|JPN|JAP', 't' => App\Models\Category::TV_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Japanese</a>
                                                    <a href="{{ route('search', ['search' => 'Korean|KR|KOR|K-Drama', 't' => App\Models\Category::TV_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Korean</a>
                                                    <a href="{{ route('search', ['search' => 'Chinese|Mandarin|Cantonese|CH|CHN|C-Drama', 't' => App\Models\Category::TV_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Chinese</a>
                                                    <a href="{{ route('search', ['search' => 'Russian|RU|RUS', 't' => App\Models\Category::TV_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Russian</a>
                                                    <a href="{{ route('search', ['search' => 'Portuguese|PT|POR|Portugues|Brazilian', 't' => App\Models\Category::TV_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Portuguese</a>
                                                </div>
                                            </div>
                                        @else
                                            <a href="{{ url('/browse/TV/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $subcat['title'] }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @elseif($parentcat['id'] == App\Models\Category::MOVIE_ROOT)
                            <div class="relative dropdown-container flex-shrink-0">
                                <button class="dropdown-toggle flex items-center px-3 py-2 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-700 dark:hover:bg-gray-800 rounded-lg transition text-sm whitespace-nowrap">
                                    <i class="fa fa-film mr-1.5"></i>
                                    <span>{{ $parentcat['title'] }}</span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="dropdown-menu absolute left-0 top-full w-48 bg-gray-900 dark:bg-gray-950 rounded-xl shadow-lg z-50">
                                    @if(auth()->check() && auth()->user()->movieview == "1")
                                        <a href="{{ url('/' . $parentcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $parentcat['title'] }}</a>
                                    @else
                                        <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $parentcat['title'] }}</a>
                                    @endif
                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                    <a href="{{ route('mymovies') }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">My Movies</a>
                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                    @foreach($parentcat['categories'] as $subcat)
                                        @if($subcat['id'] == App\Models\Category::MOVIE_FOREIGN)
                                            <div class="relative submenu-container">
                                                @if(auth()->check() && auth()->user()->movieview == "1")
                                                    <a href="{{ url('/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="submenu-toggle flex items-center justify-between px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">
                                                        <span>{{ $subcat['title'] }}</span>
                                                        <i class="fas fa-chevron-right text-xs"></i>
                                                    </a>
                                                @else
                                                    <a href="{{ url('/browse/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="submenu-toggle flex items-center justify-between px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">
                                                        <span>{{ $subcat['title'] }}</span>
                                                        <i class="fas fa-chevron-right text-xs"></i>
                                                    </a>
                                                @endif
                                                <div class="submenu absolute left-full top-0 w-40 bg-gray-900 dark:bg-gray-950 rounded-xl shadow-lg z-50 ml-0.5" style="display: none;">
                                                    @if(auth()->check() && auth()->user()->movieview == "1")
                                                        <a href="{{ url('/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">All Foreign</a>
                                                    @else
                                                        <a href="{{ url('/browse/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">All Foreign</a>
                                                    @endif
                                                    <div class="border-t border-gray-700 dark:border-gray-600"></div>
                                                    <a href="{{ route('search', ['search' => 'German|Deutsch|DE|GER', 't' => App\Models\Category::MOVIE_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">German</a>
                                                    <a href="{{ route('search', ['search' => 'French|Francais|FR|FRENCH|VOSTFR|TRUEFRENCH', 't' => App\Models\Category::MOVIE_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">French</a>
                                                    <a href="{{ route('search', ['search' => 'Spanish|Espanol|ES|ESP|SPANISH|Latino', 't' => App\Models\Category::MOVIE_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Spanish</a>
                                                    <a href="{{ route('search', ['search' => 'Italian|Italiano|IT|ITA', 't' => App\Models\Category::MOVIE_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Italian</a>
                                                    <a href="{{ route('search', ['search' => 'Dutch|NL|Flemish|Nederlands|DUTCH', 't' => App\Models\Category::MOVIE_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Dutch</a>
                                                    <a href="{{ route('search', ['search' => 'Japanese|JP|JPN|JAP', 't' => App\Models\Category::MOVIE_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Japanese</a>
                                                    <a href="{{ route('search', ['search' => 'Korean|KR|KOR|K-Drama', 't' => App\Models\Category::MOVIE_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Korean</a>
                                                    <a href="{{ route('search', ['search' => 'Chinese|Mandarin|Cantonese|CH|CHN|C-Drama', 't' => App\Models\Category::MOVIE_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Chinese</a>
                                                    <a href="{{ route('search', ['search' => 'Russian|RU|RUS', 't' => App\Models\Category::MOVIE_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Russian</a>
                                                    <a href="{{ route('search', ['search' => 'Portuguese|PT|POR|Portugues|Brazilian', 't' => App\Models\Category::MOVIE_FOREIGN]) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">Portuguese</a>
                                                </div>
                                            </div>
                                        @elseif(auth()->check() && auth()->user()->movieview == "1")
                                            <a href="{{ url('/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $subcat['title'] }}</a>
                                        @else
                                            <a href="{{ url('/browse/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2 text-sm text-gray-300 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-800 hover:text-white dark:hover:text-white">{{ $subcat['title'] }}</a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @elseif($parentcat['id'] == App\Models\Category::GAME_ROOT)
                            <div class="relative dropdown-container flex-shrink-0">
                                <button class="dropdown-toggle flex items-center px-3 py-2 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-700 dark:hover:bg-gray-800 rounded-lg transition text-sm whitespace-nowrap">
                                    <i class="fa fa-gamepad mr-1.5"></i>
                                    <span>{{ $parentcat['title'] }}</span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="dropdown-menu absolute left-0 top-full w-48 bg-gray-900 dark:bg-gray-950 rounded-xl shadow-lg z-50">
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
                            <div class="relative dropdown-container flex-shrink-0">
                                <button class="dropdown-toggle flex items-center px-3 py-2 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-700 dark:hover:bg-gray-800 rounded-lg transition text-sm whitespace-nowrap">
                                    <i class="fa fa-desktop mr-1.5"></i>
                                    <span>{{ $parentcat['title'] }}</span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="dropdown-menu absolute left-0 top-full w-48 bg-gray-900 dark:bg-gray-950 rounded-xl shadow-lg z-50">
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
                            @php
                                $desktopIconMap = [
                                    App\Models\Category::MUSIC_ROOT => 'fa-music',
                                    App\Models\Category::BOOKS_ROOT => 'fa-book',
                                    App\Models\Category::XXX_ROOT => 'fa-ban',
                                ];
                                $desktopIcon = $desktopIconMap[$parentcat['id']] ?? 'fa-folder';
                            @endphp
                            <div class="relative dropdown-container flex-shrink-0">
                                <button class="dropdown-toggle flex items-center px-3 py-2 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-700 dark:hover:bg-gray-800 rounded-lg transition text-sm whitespace-nowrap">
                                    <i class="fa {{ $desktopIcon }} mr-1.5"></i>
                                    <span>{{ $parentcat['title'] }}</span>
                                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                                </button>
                                <div class="dropdown-menu absolute left-0 top-full w-48 bg-gray-900 dark:bg-gray-950 rounded-xl shadow-lg z-50">
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
            <div class="flex items-center space-x-2 lg:space-x-4 ml-auto">
                <!-- Mobile Search Toggle (visible below lg) -->
                <button type="button" class="lg:hidden text-gray-300 hover:text-white p-2 touch-target" id="mobile-search-toggle">
                    <i class="fa fa-search text-lg"></i>
                </button>

                <!-- Desktop Search Form with Autocomplete (visible at lg+) -->
                <form method="GET" action="{{ route('search') }}" class="hidden lg:flex items-center relative" id="header-search-form">
                    <select name="t" class="bg-gray-700 text-white text-sm rounded-l-lg px-3 py-2 border-r border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                        <input type="search"
                               name="search"
                               id="header-search-input"
                               value="{{ $header_menu_search ?? '' }}"
                               placeholder="Search..."
                               autocomplete="off"
                               class="bg-gray-700 text-white text-sm px-3 py-2 w-40 xl:w-48 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <!-- Autocomplete dropdown for header -->
                        <div id="header-autocomplete-dropdown" class="hidden absolute z-50 w-64 mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto right-0">
                        </div>
                    </div>
                    <button type="submit" class="bg-green-600 dark:bg-green-700 hover:bg-green-700 dark:hover:bg-green-800 text-white px-4 py-2 rounded-r-lg transition">
                        <i class="fa fa-search"></i>
                    </button>
                </form>

                <!-- User Menu -->
                @auth
                    <div class="relative dropdown-container">
                        <button class="dropdown-toggle flex items-center space-x-2 text-gray-300 hover:text-white px-2 lg:px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                            <span class="w-8 h-8 bg-blue-600 dark:bg-blue-700 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                {{ strtoupper(substr(auth()->user()->username, 0, 1)) }}
                            </span>
                            <span class="hidden lg:block text-sm">{{ auth()->user()->username }}</span>
                            <i class="fas fa-chevron-down text-xs hidden lg:inline"></i>
                        </button>
                        <div class="dropdown-menu absolute right-0 top-full w-56 bg-gray-900 rounded-xl shadow-lg z-50">
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
                        <a href="{{ route('login') }}" class="px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700 rounded-lg transition">
                            <i class="fa fa-sign-in mr-1"></i><span class="hidden sm:inline">Login</span>
                        </a>
                        <a href="{{ route('register') }}" class="px-3 py-2 text-sm bg-green-600 dark:bg-green-700 hover:bg-green-700 dark:hover:bg-green-800 text-white rounded-lg transition">
                            <i class="fa fa-user-plus mr-1"></i><span class="hidden sm:inline">Register</span>
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </div>

    <!-- Mobile Search Form (slides down below header, visible below lg) -->
    <div id="mobile-search-form" class="lg:hidden hidden bg-gray-800 dark:bg-gray-950 border-t border-gray-700 dark:border-gray-600 shadow-lg z-50">
        <div class="container mx-auto px-4 py-3">
            <form method="GET" action="{{ route('search') }}" class="space-y-3" id="mobile-search-form-el">
                <select name="t" class="w-full bg-gray-700 text-white text-sm rounded-lg px-3 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 touch-target">
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
                    <input type="search"
                           name="search"
                           id="mobile-search-input"
                           value="{{ $header_menu_search ?? '' }}"
                           placeholder="Search releases..."
                           autocomplete="off"
                           class="w-full bg-gray-700 text-white text-sm px-3 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 touch-target">
                    <!-- Autocomplete dropdown for mobile -->
                    <div id="mobile-autocomplete-dropdown" class="hidden absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    </div>
                </div>
                <button type="submit" class="w-full bg-green-600 dark:bg-green-700 hover:bg-green-700 dark:hover:bg-green-800 text-white px-4 py-3 rounded-lg transition touch-target font-semibold">
                    <i class="fa fa-search mr-2"></i>Search
                </button>
            </form>
        </div>
    </div>

    <!-- Mobile Navigation Panel (slides down below header, visible below lg) -->
    <div id="mobile-nav-panel" class="lg:hidden hidden bg-gray-800 dark:bg-gray-950 border-t border-gray-700 dark:border-gray-600 shadow-lg z-50 max-h-[70vh] overflow-y-auto overscroll-contain">
        <div class="container mx-auto px-4 py-3">
            @if(isset($parentcatlist))
                <div class="space-y-1">
                    @foreach($parentcatlist as $parentcat)
                        <div class="mobile-nav-section">
                            @php
                                $iconMap = [
                                    App\Models\Category::TV_ROOT => 'fa-television',
                                    App\Models\Category::MOVIE_ROOT => 'fa-film',
                                    App\Models\Category::GAME_ROOT => 'fa-gamepad',
                                    App\Models\Category::PC_ROOT => 'fa-desktop',
                                    App\Models\Category::MUSIC_ROOT => 'fa-music',
                                    App\Models\Category::BOOKS_ROOT => 'fa-book',
                                    App\Models\Category::XXX_ROOT => 'fa-ban',
                                ];
                                $catIcon = $iconMap[$parentcat['id']] ?? 'fa-folder';
                            @endphp
                            <button type="button" class="mobile-nav-toggle w-full flex items-center justify-between px-4 py-3 text-gray-200 hover:bg-gray-700 dark:hover:bg-gray-800 rounded-lg transition touch-target">
                                <div class="flex items-center">
                                    <i class="fa {{ $catIcon }} fa-fw mr-3 text-gray-400"></i>
                                    <span class="font-medium">{{ $parentcat['title'] }}</span>
                                </div>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200 mobile-nav-chevron"></i>
                            </button>
                            <div class="mobile-nav-submenu hidden ml-4 mt-1 space-y-0.5 pb-1">
                                @if($parentcat['id'] == App\Models\Category::TV_ROOT)
                                    <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">All TV</a>
                                    <a href="{{ route('series') }}" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">TV Series</a>
                                @elseif($parentcat['id'] == App\Models\Category::MOVIE_ROOT)
                                    @if(auth()->check() && auth()->user()->movieview == "1")
                                        <a href="{{ url('/' . $parentcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $parentcat['title'] }}</a>
                                    @else
                                        <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $parentcat['title'] }}</a>
                                    @endif
                                    <a href="{{ route('mymovies') }}" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">My Movies</a>
                                @elseif($parentcat['id'] == App\Models\Category::GAME_ROOT)
                                    @if(auth()->check() && auth()->user()->consoleview == "1")
                                        <a href="{{ url('/' . $parentcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $parentcat['title'] }}</a>
                                    @else
                                        <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $parentcat['title'] }}</a>
                                    @endif
                                @elseif($parentcat['id'] == App\Models\Category::PC_ROOT)
                                    <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $parentcat['title'] }}</a>
                                @else
                                    <a href="{{ url('/browse/' . $parentcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">All {{ $parentcat['title'] }}</a>
                                @endif

                                @if(isset($parentcat['categories']))
                                    @foreach($parentcat['categories'] as $subcat)
                                        @if($parentcat['id'] == App\Models\Category::TV_ROOT)
                                            <a href="{{ url('/browse/TV/' . $subcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-400 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $subcat['title'] }}</a>
                                        @elseif($parentcat['id'] == App\Models\Category::MOVIE_ROOT)
                                            @if(auth()->check() && auth()->user()->movieview == "1")
                                                <a href="{{ url('/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-400 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $subcat['title'] }}</a>
                                            @else
                                                <a href="{{ url('/browse/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-400 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $subcat['title'] }}</a>
                                            @endif
                                        @elseif($parentcat['id'] == App\Models\Category::GAME_ROOT)
                                            @if(auth()->check() && auth()->user()->consoleview == "1")
                                                <a href="{{ url('/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-400 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $subcat['title'] }}</a>
                                            @else
                                                <a href="{{ url('/browse/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-400 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $subcat['title'] }}</a>
                                            @endif
                                        @elseif($parentcat['id'] == App\Models\Category::PC_ROOT && auth()->check() && auth()->user()->gameview == "1" && $subcat['id'] == App\Models\Category::PC_GAMES)
                                            <a href="{{ url('/' . $subcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-400 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $subcat['title'] }}</a>
                                        @else
                                            <a href="{{ url('/browse/' . $parentcat['title'] . '/' . $subcat['title']) }}" class="block px-4 py-2.5 text-sm text-gray-400 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">{{ $subcat['title'] }}</a>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Mobile User Quick Links -->
            @auth
                <div class="border-t border-gray-700 dark:border-gray-600 mt-3 pt-3 space-y-0.5">
                    <a href="{{ url('/cart/index') }}" class="flex items-center px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">
                        <i class="fa fa-shopping-basket fa-fw mr-3 text-gray-400"></i>My Download Basket
                    </a>
                    <a href="{{ route('profileedit') }}" class="flex items-center px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">
                        <i class="fa fa-cog fa-fw mr-3 text-gray-400"></i>Account Settings
                    </a>
                    @if(auth()->user()->hasRole('Admin'))
                        <a href="{{ url('/admin/index') }}" class="flex items-center px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 dark:hover:bg-gray-800 hover:text-white rounded-lg transition touch-target">
                            <i class="fa fa-cogs fa-fw mr-3 text-gray-400"></i>Admin
                        </a>
                    @endif
                </div>
            @endauth
        </div>
    </div>
</nav>
