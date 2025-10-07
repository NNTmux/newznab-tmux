<div class="space-y-2 px-4">
    <!-- Dashboard -->
    <a href="{{ route('admin.index') }}" class="flex items-center space-x-3 text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 py-2 px-3 rounded transition">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
    </a>

    <!-- Users -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white transition" onclick="toggleAdminSubmenu('users-menu')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="users-menu-icon"></i>
        </button>
        <div id="users-menu" class="hidden mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/user-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">User List</a>
            <a href="{{ url('/admin/role-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Roles</a>
            <a href="{{ url('/admin/deleted-users') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Deleted Users</a>
        </div>
    </div>

    <!-- Content -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white transition" onclick="toggleAdminSubmenu('content-menu')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-file-alt"></i>
                <span>Content</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="content-menu-icon"></i>
        </button>
        <div id="content-menu" class="hidden mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/content-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Content List</a>
            <a href="{{ url('/admin/content-add') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Add Content</a>
        </div>
    </div>

    <!-- Releases -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white transition" onclick="toggleAdminSubmenu('releases-menu')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-download"></i>
                <span>Releases</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="releases-menu-icon"></i>
        </button>
        <div id="releases-menu" class="hidden mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/release-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Release List</a>
            <a href="{{ url('/admin/failrel-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Failed Releases</a>
            <a href="{{ url('/admin/category-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Categories</a>
        </div>
    </div>

    <!-- Blacklist -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white transition" onclick="toggleAdminSubmenu('blacklist-menu')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-ban"></i>
                <span>Blacklist</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="blacklist-menu-icon"></i>
        </button>
        <div id="blacklist-menu" class="hidden mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/binaryblacklist-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Binary Blacklist</a>
        </div>
    </div>

    <!-- Regexes -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white transition" onclick="toggleAdminSubmenu('regexes-menu')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-code"></i>
                <span>Regexes</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="regexes-menu-icon"></i>
        </button>
        <div id="regexes-menu" class="hidden mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/category_regexes-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Category Regexes</a>
            <a href="{{ url('/admin/collection_regexes-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Collection Regexes</a>
            <a href="{{ url('/admin/release_naming_regexes-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Release Naming Regexes</a>
        </div>
    </div>

    <!-- Groups -->
    <div class="mb-4">
        <button type="button" class="flex items-center justify-between w-full text-left text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-white transition" onclick="toggleAdminSubmenu('groups-menu')">
            <div class="flex items-center space-x-3">
                <i class="fas fa-server"></i>
                <span>Groups Management</span>
            </div>
            <i class="fas fa-chevron-down text-sm transform transition-transform" id="groups-menu-icon"></i>
        </button>
        <div id="groups-menu" class="hidden mt-2 ml-6 space-y-1">
            <a href="{{ url('/admin/group-list') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">All Groups</a>
            <a href="{{ url('/admin/group-list-active') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Active Groups</a>
            <a href="{{ url('/admin/group-list-inactive') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Inactive Groups</a>
            <a href="{{ url('/admin/group-bulk') }}" class="block py-2 px-3 text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-white hover:bg-gray-800 dark:hover:bg-gray-800 rounded transition">Bulk Add Groups</a>
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
            <a href="{{ url('/admin/site-edit') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Site Settings</a>
            <a href="{{ url('/admin/tmux-edit') }}" class="block py-2 px-3 text-gray-400 hover:text-white hover:bg-gray-800 rounded transition">Tmux</a>
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

