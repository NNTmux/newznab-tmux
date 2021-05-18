<a href="#submenu1" data-bs-toggle="collapse" aria-expanded="false"
   class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
    <div class="d-flex w-100 justify-content-start align-items-center">
        <span class="fa fa-dashboard fa-fw mr-3"></span>
        <span class="menu-collapsed">Browse</span>
        <span class="submenu-icon ml-auto"></span>
    </div>
</a>
<div id='submenu1' class="collapse sidebar-submenu">
    {if $userdata->hasPermissionTo('view console') == true && $userdata->hasDirectPermission('view console') == true}
        <a href="{{route('Console')}}" class="list-group-item list-group-item-action bg-dark text-white">
            <span class="fa fa-gamepad"></span>
            <span class="menu-collapsed">Console</span>
        </a>
    {/if}
    {if $userdata->hasPermissionTo('view movies') == true && $userdata->hasDirectPermission('view movies') == true}
        <a href="{{route('Movies')}}" class="list-group-item list-group-item-action bg-dark text-white">
            <span class="fa fa-film"></span>
            <span class="menu-collapsed">Movies</span>
        </a>
    {/if}
    {if $userdata->hasPermissionTo('view audio') == true && $userdata->hasDirectPermission('view audio') == true}
        <a href="{{route('Audio')}}" class="list-group-item list-group-item-action bg-dark text-white">
            <span class="fa fa-music"></span>
            <span class="menu-collapsed">Audio</span>
        </a>
    {/if}
    {if $userdata->hasPermissionTo('view pc') == true && $userdata->hasDirectPermission('view pc') == true}
        <a href="{{route('Games')}}" class="list-group-item list-group-item-action bg-dark text-white">
            <span class="fa fa-gamepad"></span>
            <span class="menu-collapsed">Games</span>
        </a>
    {/if}
    {if $userdata->hasPermissionTo('view tv') == true && $userdata->hasDirectPermission('view tv') == true}
        <a href="{{route('series')}}" class="list-group-item list-group-item-action bg-dark text-white">
            <span class="fa fa-television"></span>
            <span class="menu-collapsed">TV</span>
        </a>
    {/if}
    {if $userdata->hasPermissionTo('view adult') == true && $userdata->hasDirectPermission('view adult') == true}
        <a href="{{route('XXX')}}" class="list-group-item list-group-item-action bg-dark text-white">
            <span class="fa fa-venus-mars"></span>
            <span class="menu-collapsed">Adult</span>
        </a>
    {/if}
    {if $userdata->hasPermissionTo('view books') == true && $userdata->hasDirectPermission('view books') == true}
        <a href="{{route('Books')}}" class="list-group-item list-group-item-action bg-dark text-white">
            <span class="fa fa-book-open"></span>
            <span class="menu-collapsed">Books</span>
        </a>
    {/if}
    <a href="{{url('browse/All')}}" class="list-group-item list-group-item-action bg-dark text-white">
        <span class="fa fa-list-ul"></span>
        <span class="menu-collapsed">Browse All Releases</span>
    </a>
    <a href="{{route('browsegroup')}}" class="list-group-item list-group-item-action bg-dark text-white">
        <span class="fa fa-layer-group"></span>
        <span class="menu-collapsed">Browse Groups</span>
    </a>
</div>
<a href="#submenu2" data-bs-toggle="collapse" aria-expanded="false"
   class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white">
    <div class="d-flex w-100 justify-content-start align-items-center">
        <span class="fa fa-edit fa-fw mr-3"></span>
        <span class="menu-collapsed">Articles & Links</span>
        <span class="submenu-icon ml-auto"></span>
    </div>
</a>
<!-- Submenu content -->
<div id='submenu2' class="collapse sidebar-submenu">
    <a href="{{route('forum')}}" class="list-group-item list-group-item-action bg-dark text-white">
        <span class="fa fa-forumbee"></span>
        <span class="menu-collapsed">Forum</span>
    </a>
    <a href="{{route('search')}}" class="list-group-item list-group-item-action bg-dark text-white">
        <span class="fa fa-searchengin"></span>
        <span class="menu-collapsed">Search</span>
    </a>
    <a href="{{url('search?search_type=adv')}}" class="list-group-item list-group-item-action bg-dark text-white">
        <span class="fa fa-searchengin"></span>
        <span class="menu-collapsed">Advanced Search</span>
    </a>
    <a href="{{route('rsshelp')}}" class="list-group-item list-group-item-action bg-dark text-white">
        <span class="fa fa-rss-square"></span>
        <span class="menu-collapsed">RSS Feeds</span>
    </a>
    <a href="{{route('apihelp')}}" class="list-group-item list-group-item-action bg-dark text-white">
        <span class="fa fa-hire-a-helper"></span>
        <span class="menu-collapsed">API</span>
    </a>
    <a href="{{route('apiv2help')}}" class="list-group-item list-group-item-action bg-dark text-white">
        <span class="fa fa-hire-a-helper"></span>
        <span class="menu-collapsed">API V2</span>
    </a>
</div>
<a href="{{route('logout')}}"
   class="bg-transparent list-group-item list-group-item-action flex-column align-items-start text-white"
   onclick="event.preventDefault(); document.getElementById('frm-logout').submit();">
    <span class="fa fa-unlock mr-3"></span>
    <span>Sign Out</span>
</a>
