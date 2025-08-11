<div id="sidebar-container" class="sidebar-expanded d-none d-md-block">
    <ul class="list-group">
        <!-- Home links section -->
        <li class="bg-transparent list-group-item sidebar-separator-title d-flex align-items-center menu-collapsed">
            <a title="Home" href="{{url("{$site->home_link}")}}" class="text-decoration-none text-white"><small>HOME</small></a>
        </li>
        <li class="bg-transparent list-group-item sidebar-separator-title d-flex align-items-center menu-collapsed">
            <a title="Admin Home" href="{{url("/admin/index")}}" class="text-decoration-none text-white"><small>ADMIN HOME</small></a>
        </li>
        <li class="bg-transparent list-group-item sidebar-separator-title d-flex align-items-center menu-collapsed">
            <small class="text-white-50">ADMIN FUNCTIONS</small>
        </li>

        <!-- Site Management -->
        <a href="#submenu1" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-sitemap fa-fw me-3"></span>
                <span class="menu-collapsed">Edit Site</span>
                <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
            </div>
        </a>
        <div id='submenu1' class="collapse sidebar-submenu">
            <a href="{{url("/admin/site-edit")}}" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Edit Site</span>
            </a>
        </div>


        <!-- Content Management -->
                <a href="#submenu2" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-plus-square-o fa-fw me-3"></span>
                        <span class="menu-collapsed">Content</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu2' class="collapse sidebar-submenu">
                    <a href="{{url("/admin/content-add?action=add")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Add Content</span>
                    </a>
                    <a href="{{url("/admin/content-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Edit Content</span>
                    </a>
                </div>

                <!-- Categories Management -->
                <a href="#submenu3" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-tint fa-fw me-3"></span>
                        <span class="menu-collapsed">Categories</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu3' class="collapse sidebar-submenu">
                    <a href="{{url("/admin/category-list?action=add")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Edit Categories</span>
                    </a>
                </div>

                <!-- Groups Management -->
                <a href="#submenu4" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-object-group fa-fw me-3"></span>
                        <span class="menu-collapsed">Groups</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu4' class="collapse sidebar-submenu">
                    <a href="{{url("admin/group-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Groups</span>
                    </a>
                    <a href="{{url("/admin/group-edit")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Add Groups</span>
                    </a>
                    <a href="{{url("/admin/group-bulk")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Bulk Add Groups</span>
                    </a>
                </div>

                <!-- Regexes Management -->
                <a href="#submenu5" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-registered fa-fw me-3"></span>
                        <span class="menu-collapsed">Regexes</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu5' class="collapse sidebar-submenu">
                    <a href="{{url("/admin/category_regexes-edit?action=add")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Add Category Regexes</span>
                    </a>
                    <a href="{{url("/admin/category_regexes-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Category Regexes</span>
                    </a>
                    <a href="{{url("/admin/collection_regexes-edit?action=add")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Add Collection Regexes</span>
                    </a>
                    <a href="{{url("/admin/collection_regexes-test?action=add")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Test Collection Regexes</span>
                    </a>
                    <a href="{{url("/admin/collection_regexes-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Collection Regexes</span>
                    </a>
                    <a href="{{url("/admin/release_naming_regexes-edit?action=add")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Add Release Naming Regexes</span>
                    </a>
                    <a href="{{url("/admin/release_naming_regexes-test?action=add")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Test Release Naming Regexes</span>
                    </a>
                    <a href="{{url("/admin/release_naming_regexes-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Release Naming Regexes</span>
                    </a>
                </div>

                <!-- Blacklist Management -->
                <a href="#submenu6" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-stop fa-fw me-3"></span>
                        <span class="menu-collapsed">Blacklist</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu6' class="collapse sidebar-submenu">
                    <a href="{{url("/admin/binaryblacklist-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Blacklist</span>
                    </a>
                    <a href="{{url("/admin/binaryblacklist-edit")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Add Blacklist</span>
                    </a>
                </div>

                <!-- Releases Management -->
                <a href="#submenu7" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-recycle fa-fw me-3"></span>
                        <span class="menu-collapsed">Releases</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu7' class="collapse sidebar-submenu">
                    <a href="{{url("/admin/release-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Releases</span>
                    </a>
                    <a href="{{url("/admin/failrel-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Failed Releases</span>
                    </a>
                    <a href="{{url("/admin/show-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Shows List</span>
                    </a>
                    <a href="{{url("/admin/movie-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Movie List</span>
                    </a>
                    <a href="{{url("/admin/movie-add")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Add Movie</span>
                    </a>
                    <a href="{{url("/admin/anidb-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View AniDB List</span>
                    </a>
                    <a href="{{url("/admin/game-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Games List</span>
                    </a>
                    <a href="{{url("/admin/music-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Music List</span>
                    </a>
                    <a href="{{url("/admin/console-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Console List</span>
                    </a>
                    <a href="{{url("/admin/book-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Book List</span>
                    </a>
                </div>

                <!-- NZB Management -->
                <a href="#submenu9" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-download fa-fw me-3"></span>
                        <span class="menu-collapsed">NZB</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu9' class="collapse sidebar-submenu">
                    <a href="{{url("/admin/nzb-import")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Import NZBs</span>
                    </a>
                    <a href="{{url("/admin/nzb-export")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Export NZBs</span>
                    </a>
                </div>

                <!-- Stats -->
                <a href="#submenu10" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-hourglass-start fa-fw me-3"></span>
                        <span class="menu-collapsed">Stats</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu10' class="collapse sidebar-submenu">
                    <a href="{{url("/admin/site-stats")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Site Stats</span>
                    </a>
                </div>

                <!-- Comments -->
                <a href="#submenu11" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-comment fa-fw me-3"></span>
                        <span class="menu-collapsed">Comments</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu11' class="collapse sidebar-submenu">
                    <a href="{{url("/admin/comments-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Comments</span>
                    </a>
                </div>

                <!-- Users & Roles -->
                <a href="#submenu12" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-users fa-fw me-3"></span>
                        <span class="menu-collapsed">Users & Roles</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu12' class="collapse sidebar-submenu">
                    <a href="{{url("/admin/user-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Users</span>
                    </a>
                    <a href="{{url("/admin/user-edit?action=add")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Add Users</span>
                    </a>
                    <a href="{{url("/admin/role-list")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View User Roles</span>
                    </a>
                    <a href="{{url("/admin/role-add")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Add User Roles</span>
                    </a>
                    <a href="{{url("/admin/invitations")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Manage Invitations</span>
                    </a>
                    <a href="{{url("/admin/deleted-users")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Deleted Users</span>
                    </a>
                </div>

                <!-- Tmux -->
                <a href="#submenu13" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-linux fa-fw me-3"></span>
                        <span class="menu-collapsed">Tmux</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu13' class="collapse sidebar-submenu">
                    <a href="{{url("/admin/tmux-edit")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">Tmux Settings</span>
                    </a>
                </div>

                <!-- Pre Database -->
                <a href="#submenu14" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-database fa-fw me-3"></span>
                        <span class="menu-collapsed">Pre Database</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu14' class="collapse sidebar-submenu">
                    <a href="{{url("/admin/predb")}}" class="list-group-item list-group-item-action bg-dark text-white">
                        <span class="menu-collapsed">View Pre Database</span>
                    </a>
                </div>

                <!-- Laravel Telescope -->
                <a href="#submenu15" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-eye fa-fw me-3"></span>
                        <span class="menu-collapsed">Laravel Telescope</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu15' class="collapse sidebar-submenu">
                    <a href="{{url("/telescope")}}" class="list-group-item list-group-item-action bg-dark text-white" target="_blank">
                        <span class="menu-collapsed">View Telescope Monitoring tool</span>
                    </a>
                </div>

                <!-- Laravel Horizon -->
                <a href="#submenu16" data-bs-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
                    <div class="d-flex w-100 justify-content-start align-items-center">
                        <span class="fa fa-chart-line fa-fw me-3"></span>
                        <span class="menu-collapsed">Laravel Horizon</span>
                        <span class="ms-auto"><i class="fa fa-chevron-down"></i></span>
                    </div>
                </a>
                <div id='submenu16' class="collapse sidebar-submenu">
                    <a href="{{url("/horizon")}}" class="list-group-item list-group-item-action bg-dark text-white" target="_blank">
                        <span class="menu-collapsed">View Horizon Redis Queue Monitoring tool</span>
                    </a>
                </div>
            </ul>
        </div>

        <style>
            <style>
                /* Sidebar styling */
            .list-group-item {
                border: none;
                padding: 0.5rem 1rem;
            }

            /* Section headers */
            .sidebar-separator-title small {
                font-weight: 600;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }

            /* Improved submenu styling */
            .sidebar-submenu {
                font-size: 0.9rem;
                transition: all 0.3s ease;
            }

            .sidebar-submenu .list-group-item {
                padding-left: 2rem;
                background-color: #2c3136 !important;
                border-left: 3px solid transparent;
                transition: all 0.2s ease;
            }

            .sidebar-submenu .list-group-item:hover {
                background-color: rgba(255,255,255,.1) !important;
                border-left: 3px solid #5294ff;
            }

            /* Active item styling */
            .sidebar-submenu .list-group-item.active {
                background-color: rgba(255,255,255,.15) !important;
                border-left: 3px solid #5294ff;
            }

            /* Icon transitions for dropdown indicators */
            [data-bs-toggle="collapse"] .fa-chevron-down {
                transition: transform 0.3s ease;
            }

            [aria-expanded="true"] .fa-chevron-down {
                transform: rotate(180deg);
            }

            /* Hover effects */
            .list-group-item.list-group-item-action:hover {
                background-color: rgba(255,255,255,.1) !important;
                border-radius: 3px;
            }

            /* Mobile responsiveness */
            @media (max-width: 767px) {
                #sidebar-container {
                    width: 100%;
                    max-height: calc(100vh - 60px);
                    overflow-y: auto;
                }

                /* Better touch targets for mobile */
                .list-group-item, .sidebar-submenu .list-group-item {
                    padding: 0.7rem 1rem;
                }

                /* Ensure good spacing on mobile */
                .d-flex.w-100.justify-content-start.align-items-center {
                    min-height: 40px;
                }
            }
        </style>

<script>
    {literal}
    // Add this JavaScript to handle active menu items
    document.addEventListener('DOMContentLoaded', function() {
        // Get current path
        const path = window.location.pathname;

        // Find links that match the current path
        document.querySelectorAll('.sidebar-submenu a').forEach(link => {
            const href = link.getAttribute('href');
            if (href && path.includes(href.replace("{{url('", "").replace("')}}", ""))) {
                link.classList.add('active');

                // Open the parent menu
                const parentId = link.closest('.sidebar-submenu').id;
                const parentToggle = document.querySelector(`[href="#${parentId}"]`);
                if (parentToggle) {
                    parentToggle.setAttribute('aria-expanded', 'true');
                    document.getElementById(parentId).classList.add('show');
                }
            }
        });
    });
    {/literal}
</script>
