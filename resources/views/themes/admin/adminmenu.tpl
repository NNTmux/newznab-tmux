<div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
	<div class="menu_section">
		<h3>Admin Functions</h3>
		<ul class="nav side-menu">
			<li><a title="Home" href="{$smarty.const.WWW_TOP}/..{$site->home_link}">Home</a></li>
			<li><a title="Admin Home" href="{$smarty.const.WWW_TOP}/admin/index">Admin Home</a></li>
			<li><a><i class="fa fa-sitemap"></i><span> Edit Site</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/site-edit">Edit Site</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-plus-square-o"></i><span> Content</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/content-add?action=add">Add Content</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/content-list">Edit Content</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-chevron-circle-down"></i><span> Menu</span><span
							class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/menu-list">View Menu Items</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/menu-edit?action=add">Add Menu Items</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-tint"></i><span> Categories</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/category-list?action=add">Edit Categories</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-object-group"></i><span> Groups</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/group-list">View Groups</a>
					<li><a href="{$smarty.const.WWW_TOP}/admin/group-edit">Add Groups</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/group-bulk">BulkAdd Groups</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-registered"></i><span> Regexes</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/category_regexes-edit?action=add">Add Category
							Regexes</a>
					</li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/category_regexes-list">View Category Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/collection_regexes-edit?action=add">Add Collection
							Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/collection_regexes-test?action=add">Test Collection
							Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/collection_regexes-list">View Collection Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/release_naming_regexes-edit?action=add">Add Release
							Naming
							Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/release_naming_regexes-test?action=add">Test Release
							Naming
							Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/release_naming_regexes-list">View Release Naming
							Regexes</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-stop"></i><span> Blacklist</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/binaryblacklist-list">View Blacklist</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/binaryblacklist-edit?action=add">Add Blacklist</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-recycle"></i><span> Releases</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/release-list">View Releases</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/failrel-list">View Failed Releases</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/show-list">View Shows</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/movie-list">View Movie List</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/movie-add">Add Movie</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/anidb-list">View AniDB List</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/game-list">View Games</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/music-list">View Music List</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/console-list">View Console List</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/book-list">View Book List</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-object-group"></i><span> Multi Group</span><span
							class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/posters-edit">Add MultiGroup Poster</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/posters-list">MultiGroup Posters List</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-download"></i><span> NZB</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/nzb-import">Import NZBs</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/nzb-export">Export NZBs</a></li>
				</ul>
			</li>

			<li><a><i class="fa fa-hourglass-start"></i><span> Stats & Logs</span><span
							class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/site-stats">Site Stats</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-comments"></i><span> Comments & Sharing</span><span
							class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/comments-list">View Comments</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/sharing">Comment Sharing Settings</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-users"></i><span> Users & Roles</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/user-list">View Users</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/user-edit?action=add">Add Users</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/role-list">View User Roles</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/admin/role-edit?action=add">Add User Roles</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-linux"></i><span> Tmux</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/tmux-edit">Tmux Settings</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-object-group"></i><span> Pre Database</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/admin/predb">View Predb</a></li>
				</ul>
			</li>
		</ul>
	</div>
</div>
