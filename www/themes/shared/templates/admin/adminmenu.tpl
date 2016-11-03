<div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
	<div class="menu_section">
		<h3>Admin Functions</h3>
		<ul class="nav side-menu">
			<li><a title="Home" href="{$smarty.const.WWW_TOP}/..{$site->home_link}">Home</a></li>
			<li><a title="Admin Home" href="{$smarty.const.WWW_TOP}/">Admin Home</a></li>
			<li><a><i class="fa fa-sitemap"></i><span> Edit Site</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/site-edit.php">Edit Site</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-plus-square-o"></i><span> Content</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/content-add.php?action=add">Add Content</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/content-list.php">Edit Content</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-chevron-circle-down"></i><span> Menu</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/menu-list.php">View Menu Items</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/menu-edit.php?action=add">Add Menu Items</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-tint"></i><span> Categories</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/category-list.php?action=add">Edit categories</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-object-group"></i><span> Groups</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/group-list.php">View Groups</a>
					<li><a href="{$smarty.const.WWW_TOP}/group-edit.php">Add Groups</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/group-bulk.php">BulkAdd Groups</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-registered"></i><span> Regexes</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/category_regexes-edit.php?action=add">Add Category Regex</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/category_regexes-list.php">View category Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/collection_regexes-edit.php?action=add">Add Collection Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/collection_regexes-test.php?action=add">Test Collection Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/collection_regexes-list.php">View Collection Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/release_naming_regexes-edit.php?action=add">Add Release Naming Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/release_naming_regexes-test.php?action=add">Test Release Naming Regexes</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/release_naming_regexes-list.php">View Release Naming Regexes</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-stop"></i><span> Blacklist</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/binaryblacklist-list.php">View Blacklist</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/binaryblacklist-edit.php?action=add">Add Blacklist</a></li>
					</ul>
			</li>
			<li><a><i class="fa fa-recycle"></i><span> Releases</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/release-list.php">View Releases</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/failrel-list.php">View Failed Releases</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/show-list.php">View Shows</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/movie-list.php">View Movie List</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/movie-add.php">Add Movie</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/anidb-list.php">View AniDB List</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/game-list.php">View Games</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/music-list.php">View Music List</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/console-list.php">View Console List</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/book-list.php">View Book List</a></li>
					</ul>
			</li>
			<li><a><i class="fa fa-download"></i><span> NZB</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/nzb-import.php">Import NZBs</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/nzb-export.php">Export NZBs</a></li>
					</ul>
			</li>

			<li><a><i class="fa fa-hourglass-start"></i><span> Stats & Logs</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/opcachestats.php">Opcache Statistics</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/site-stats.php">Site Stats</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/view-logs.php">View Logs</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-comments"></i><span> Comments & Sharing</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/comments-list.php">View Comments</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/spotnab-list.php">View Spotnab Sources</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/spotnab-edit.php?action=add">Add Spotnab Sources</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/sharing.php">Comment Sharing Settings</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-users"></i><span> Users & Roles</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/user-list.php">View Users</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/user-edit.php?action=add">Add Users</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/role-list.php">View User Roles</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/role-edit.php?action=add">Add User Roles</a></li>
				</ul>
			</li>
			<li><a><i class="fa fa-linux"></i><span> Tmux</span><span class="fa fa-chevron-down"></span></a>
				<ul class="nav child_menu" style="display: none">
					<li><a href="{$smarty.const.WWW_TOP}/tmux-edit.php">Tmux Settings</a></li>
				</ul>
			</li>
		</ul>
	</div>
</div>
