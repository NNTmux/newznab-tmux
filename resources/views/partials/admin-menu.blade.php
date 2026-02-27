<div class="space-y-2 px-4">
    <!-- Dashboard -->
    <div class="mb-4">
        <a href="{{ route('admin.index') }}" class="flex items-center space-x-3 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </div>

    <!-- Users -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/user-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-user-friends mr-2 text-blue-400"></i>User List
            </a>
            <a href="{{ url('/admin/role-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-user-tag mr-2 text-purple-400"></i>Roles
            </a>
            <a href="{{ url('/admin/user-role-history') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-history mr-2 text-yellow-400"></i>Role History
            </a>
            <a href="{{ url('/admin/promotions') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-arrow-up mr-2 text-green-400"></i>Promotions
            </a>
            <a href="{{ url('/admin/deleted-users') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-user-slash mr-2 text-gray-400"></i>Deleted Users
            </a>
        </div>
    </div>

    <!-- Invitations -->
    <div class="mb-4">
        <a href="{{ url('/admin/invitations') }}" class="flex items-center space-x-3 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <i class="fas fa-envelope-open-text"></i>
            <span>Invitations</span>
        </a>
    </div>

    <!-- Content -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-file-alt"></i>
                <span>Content</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/content-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-list mr-2 text-blue-400"></i>Content List
            </a>
            <a href="{{ url('/admin/content-add') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-plus-circle mr-2 text-green-400"></i>Add Content
            </a>
        </div>
    </div>

    <!-- Comments -->
    <div class="mb-4">
        <a href="{{ url('/admin/comments-list') }}" class="flex items-center space-x-3 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <i class="fas fa-comments"></i>
            <span>Comments</span>
        </a>
    </div>

    <!-- Releases -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-download"></i>
                <span>Releases</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/release-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-list-ul mr-2 text-blue-400"></i>Release List
            </a>
            <a href="{{ url('/admin/release-reports') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-flag text-red-500 mr-1"></i> Release Reports
            </a>
            <a href="{{ url('/admin/failrel-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-exclamation-triangle mr-2 text-red-400"></i>Failed Releases
            </a>
            <a href="{{ url('/admin/category-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-folder-tree mr-2 text-yellow-400"></i>Categories
            </a>
        </div>
    </div>

    <!-- Movies -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-film"></i>
                <span>Movies</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/movie-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-video mr-2 text-blue-400"></i>Movie List
            </a>
            <a href="{{ url('/admin/movie-add') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-plus-circle mr-2 text-green-400"></i>Add Movie
            </a>
        </div>
    </div>

    <!-- TV Shows -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-tv"></i>
                <span>TV Shows</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/show-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-list mr-2 text-blue-400"></i>TV Shows List
            </a>
        </div>
    </div>

    <!-- AniDB -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-dragon"></i>
                <span>AniDB</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/anidb-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-list mr-2 text-pink-400"></i>AniDB List
            </a>
        </div>
    </div>

    <!-- Games -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-gamepad"></i>
                <span>Games</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/game-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-dice mr-2 text-green-400"></i>Game List
            </a>
        </div>
    </div>

    <!-- Console -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-desktop"></i>
                <span>Console</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/console-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-list mr-2 text-purple-400"></i>Console List
            </a>
        </div>
    </div>

    <!-- Music -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-music"></i>
                <span>Music</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/music-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-compact-disc mr-2 text-cyan-400"></i>Music List
            </a>
        </div>
    </div>

    <!-- Books -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-book"></i>
                <span>Books</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/book-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-book-open mr-2 text-amber-400"></i>Book List
            </a>
        </div>
    </div>

    <!-- PreDB -->
    <div class="mb-4">
        <a href="{{ url('/admin/predb') }}" class="flex items-center space-x-3 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <i class="fas fa-database"></i>
            <span>PreDB</span>
        </a>
    </div>

    <!-- Blacklist -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-ban"></i>
                <span>Blacklist</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/binaryblacklist-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-file-circle-xmark mr-2 text-red-400"></i>Binary Blacklist
            </a>
        </div>
    </div>

    <!-- Regexes -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-code"></i>
                <span>Regexes</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/category_regexes-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-folder mr-2 text-yellow-400"></i>Category Regexes
            </a>
            <a href="{{ url('/admin/collection_regexes-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-layer-group mr-2 text-blue-400"></i>Collection Regexes
            </a>
            <a href="{{ url('/admin/release_naming_regexes-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-tag mr-2 text-green-400"></i>Release Naming Regexes
            </a>
        </div>
    </div>

    <!-- Groups -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-server"></i>
                <span>Groups Management</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/group-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-list mr-2 text-blue-400"></i>All Groups
            </a>
            <a href="{{ url('/admin/group-list-active') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-check-circle mr-2 text-green-400"></i>Active Groups
            </a>
            <a href="{{ url('/admin/group-list-inactive') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-pause-circle mr-2 text-gray-400"></i>Inactive Groups
            </a>
            <a href="{{ url('/admin/group-bulk') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-plus-square mr-2 text-purple-400"></i>Bulk Add Groups
            </a>
        </div>
    </div>

    <!-- System -->
    <div class="mb-4" x-data="adminSubmenu">
        <button type="button" x-on:click="toggle()" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
            <div class="flex items-center space-x-3">
                <i class="fas fa-cog"></i>
                <span>System</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" x-bind:class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/site-edit') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-sliders-h mr-2 text-blue-400"></i>Site Settings
            </a>
            <a href="{{ url('/admin/tmux-edit') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-terminal mr-2 text-green-400"></i>Tmux
            </a>
            <a href="{{ url('/admin/site-stats') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-chart-bar mr-2 text-purple-400"></i>Statistics
            </a>
            <a href="{{ url(config('horizon.path', 'horizon')) }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">
                <i class="fas fa-stream mr-2 text-cyan-400"></i>Horizon
            </a>
        </div>
    </div>

</div>

