<div class="space-y-2 px-4">
    <!-- Dashboard -->
    <a href="{{ url('/admin') }}" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-800 py-2 px-3 rounded transition">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
    </a>

    <!-- Users -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 hover:text-white transition" onclick="toggleAdminSubmenu('users-menu')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="users-menu-icon"></i>
        </button>
        <div id="users-menu" class="hidden mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/user-list') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">User List</a>
            <a href="{{ url('/admin/role-list') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Roles</a>
            <a href="{{ url('/admin/user-deleted') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Deleted Users</a>
        </div>
    </div>

    <!-- Content -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 hover:text-white transition" onclick="toggleAdminSubmenu('content-menu')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-file-alt"></i>
                <span>Content</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="content-menu-icon"></i>
        </button>
        <div id="content-menu" class="hidden mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/content-list') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Content List</a>
            <a href="{{ url('/admin/content-add') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Add Content</a>
        </div>
    </div>

    <!-- Releases -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 hover:text-white transition" onclick="toggleAdminSubmenu('releases-menu')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-download"></i>
                <span>Releases</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="releases-menu-icon"></i>
        </button>
        <div id="releases-menu" class="hidden mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/release-list') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Release List</a>
            <a href="{{ url('/admin/failed-releases') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Failed Releases</a>
            <a href="{{ url('/admin/category-list') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Categories</a>
        </div>
    </div>

    <!-- System -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 hover:text-white transition" onclick="toggleAdminSubmenu('system-menu')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-cog"></i>
                <span>System</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="system-menu-icon"></i>
        </button>
        <div id="system-menu" class="hidden mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/site-settings') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Site Settings</a>
            <a href="{{ url('/admin/tmux') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Tmux</a>
            <a href="{{ url('/admin/site-stats') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Statistics</a>
        </div>
    </div>
</div>

<script>
function toggleAdminSubmenu(id) {
    const submenu = document.getElementById(id);
    const icon = document.getElementById(id + '-icon');
    submenu.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}
</script>
<div class="space-y-2 px-4">
    <!-- Browse Section -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 hover:text-white transition" onclick="toggleSubmenu('submenu1')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-dashboard"></i>
                <span>Browse</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="submenu1-icon"></i>
        </button>
        <div id="submenu1" class="hidden mt-2 ml-6 space-y-1">
            @can('view console')
                <a href="{{ route('Console') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-gamepad mr-2"></i> Console
                </a>
            @endcan
            @can('view movies')
                <a href="{{ route('Movies') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-film mr-2"></i> Movies
                </a>
            @endcan
            @can('view audio')
                <a href="{{ route('Audio') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-music mr-2"></i> Audio
                </a>
            @endcan
            @can('view pc')
                <a href="{{ route('Games') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-gamepad mr-2"></i> Games
                </a>
            @endcan
            @can('view tv')
                <a href="{{ route('series') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-television mr-2"></i> TV
                </a>
            @endcan
            @can('view adult')
                <a href="{{ route('XXX') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-venus-mars mr-2"></i> Adult
                </a>
            @endcan
            @can('view books')
                <a href="{{ route('Books') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                    <i class="fa fa-book-open mr-2"></i> Books
                </a>
            @endcan
            <a href="{{ url('browse/All') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-list-ul mr-2"></i> Browse All Releases
            </a>
            <a href="{{ route('browsegroup') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-layer-group mr-2"></i> Browse Groups
            </a>
        </div>
    </div>

    <!-- Articles & Links Section -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 hover:text-white transition" onclick="toggleSubmenu('submenu2')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-edit"></i>
                <span>Articles & Links</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="submenu2-icon"></i>
        </button>
        <div id="submenu2" class="hidden mt-2 ml-6 space-y-1">
            <a href="{{ url('forum') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-forumbee mr-2"></i> Forum
            </a>
            <a href="{{ route('search') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-searchengin mr-2"></i> Search
            </a>
            <a href="{{ url('search?search_type=adv') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-searchengin mr-2"></i> Advanced Search
            </a>
            <a href="{{ route('rsshelp') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-rss-square mr-2"></i> RSS Feeds
            </a>
            <a href="{{ route('apihelp') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-hire-a-helper mr-2"></i> API
            </a>
            <a href="{{ route('apiv2help') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">
                <i class="fa fa-hire-a-helper mr-2"></i> API V2
            </a>
        </div>
    </div>

    <!-- User Section -->
    @auth
        <div class="mb-4 border-t border-gray-700 pt-4">
            <a href="{{ route('profile') }}" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-800 py-2 px-3 rounded transition">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            @if(auth()->user()->hasRole('Admin'))
                <a href="{{ url('/admin') }}" class="flex items-center space-x-3 text-gray-300 hover:text-white hover:bg-gray-800 py-2 px-3 rounded transition">
                    <i class="fas fa-cog"></i>
                    <span>Admin Panel</span>
                </a>
            @endif
            <a href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('frm-logout').submit();"
               class="flex items-center space-x-3 text-red-400 hover:text-red-300 hover:bg-gray-800 py-2 px-3 rounded transition">
                <i class="fas fa-unlock"></i>
                <span>Sign Out</span>
            </a>
            <form id="frm-logout" action="{{ route('logout') }}" method="POST" class="hidden">
                @csrf
            </form>
        </div>
    @endauth
</div>

<script>
function toggleSubmenu(id) {
    const submenu = document.getElementById(id);
    const icon = document.getElementById(id + '-icon');
    submenu.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}
</script>

