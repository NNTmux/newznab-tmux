<div id="sidebar-container" class="sidebar-expanded d-none d-md-block">
    <ul class="list-group">
        <li class="bg-transparent list-group-item sidebar-separator-title text-muted d-flex align-items-center menu-collapsed">
            <a title="Home" href="{$smarty.const.WWW_TOP}/..{$site->home_link}"><small>HOME</small></a>
        </li>
        <li class="bg-transparent list-group-item sidebar-separator-title text-muted d-flex align-items-center menu-collapsed">
            <a title="Admin Home" href="{$smarty.const.WWW_TOP}/admin/index"><small>ADMIN HOME</small></a>
        </li>
        <li class="bg-transparent list-group-item sidebar-separator-title text-muted d-flex align-items-center menu-collapsed">
            <small>ADMIN FUNCTIONS</small>
        </li>
        <a href="#submenu1" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-sitemap fa-fw mr-3"></span>
                <span class="menu-collapsed">Edit Site</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu1' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/site-edit" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Edit Site</span>
            </a>
        </div>
        <a href="#submenu2" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-plus-square-o fa-fw mr-3"></span>
                <span class="menu-collapsed">Content</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu2' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/content-add?action=add" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Add Content</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/content-list" class="list-group-item list-group-item-action bg-dark text-white">
            <span class="menu-collapsed">Edit Content</span>
            </a>
        </div>
        <a href="#submenu3" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-tint fa-fw mr-3"></span>
                <span class="menu-collapsed">Categories</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu3' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/category-list?action=add" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Edit Categories</span>
            </a>
        </div>
        <a href="#submenu4" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-object-group fa-fw mr-3"></span>
                <span class="menu-collapsed">Groups</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu4' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/group-list" class="list-group-item list-group-item-action bg-dark text-white">
            <span class="menu-collapsed">View Groups</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/group-edit" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Add Groups</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/group-bulk" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Bulk Add Groups</span>
            </a>
        </div>
        <a href="#submenu5" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-registered fa-fw mr-3"></span>
                <span class="menu-collapsed">Regexess</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu5' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/category_regexes-edit?action=add" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Add Category Regexes</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/category_regexes-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Category Regexes</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/collection_regexes-edit?action=add" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Add Collection Regexes</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/collection_regexes-test?action=add" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Test Collection Regexes</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/collection_regexes-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Collection Regexes</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/release_naming_regexes-edit?action=add" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Add Release Naming Regexes</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/release_naming_regexes-test?action=add" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Test Release Naming Regexes</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/release_naming_regexes-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Release Naming Regexes</span>
            </a>
        </div>
        <a href="#submenu6" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-stop fa-fw mr-3"></span>
                <span class="menu-collapsed">Blacklist</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu6' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/binaryblacklist-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Blacklist</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/binaryblacklist-edit?action=add" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Add Blacklist</span>
            </a>
        </div>
        <a href="#submenu7" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-recycle fa-fw mr-3"></span>
                <span class="menu-collapsed">Releases</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu7' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/release-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Releases</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/failrel-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Failed Releases</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/show-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Shows List</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/movie-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Movie List</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/movie-add" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Add Movie</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/anidb-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View AniDB List</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/game-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Games List</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/music-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Music List</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/console-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Console List</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/book-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Book List</span>
            </a>
        </div>
        <a href="#submenu8" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-object-group fa-fw mr-3"></span>
                <span class="menu-collapsed">Multi Group</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu8' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/posters-edit" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Add MultiGroup poster</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/posters-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View MultiGroup Posters List</span>
            </a>
        </div>
        <a href="#submenu9" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-download fa-fw mr-3"></span>
                <span class="menu-collapsed">NZB</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu9' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/nzb-import" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Import NZBs</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/nzb-export" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Export NZBs</span>
            </a>
        </div>
        <a href="#submenu10" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-hourglass-start fa-fw mr-3"></span>
                <span class="menu-collapsed">Stats</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu10' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/site-stats" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Site Stats</span>
            </a>
        </div>
        <a href="#submenu11" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-comment fa-fw mr-3"></span>
                <span class="menu-collapsed">Comments & Sharing</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu11' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/comments-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Comments</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/sharing" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Comment Sharing Settings</span>
            </a>
        </div>
        <a href="#submenu12" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-users fa-fw mr-3"></span>
                <span class="menu-collapsed">Users & Roles</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu12' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/user-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Users</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/user-edit?action=add" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Add Users</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/role-list" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View User Roles</span>
            </a>
            <a href="{$smarty.const.WWW_TOP}/admin/role-add" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Add User Roles</span>
            </a>
        </div>
        <a href="#submenu13" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-linux fa-fw mr-3"></span>
                <span class="menu-collapsed">Tmux</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu13' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/tmux-edit" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">Tmux Settings</span>
            </a>
        </div>
        <a href="#submenu14" data-toggle="collapse" aria-expanded="false" class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
            <div class="d-flex w-100 justify-content-start align-items-center">
                <span class="fa fa-database fa-fw mr-3"></span>
                <span class="menu-collapsed">Pre Database</span>
                <span class="submenu-icon ml-auto"></span>
            </div>
        </a>
        <div id='submenu13' class="collapse sidebar-submenu">
            <a href="{$smarty.const.WWW_TOP}/admin/predb" class="list-group-item list-group-item-action bg-dark text-white">
                <span class="menu-collapsed">View Pre Database</span>
            </a>
        </div>
    </ul>
</div>
