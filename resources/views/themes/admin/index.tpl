<div class="well well-sm">
	<h1>{$page->title}</h1>

	<div style="{if $site->showadminwelcome != "1"}display:none;{/if}" id="adminwelcome">
		<p>
			Welcome to NNTmux. In this area you will be able to configure many aspects of your site.<br>
		</p>

		<ol style="list-style-type:decimal; line-height: 180%;">
			<li style="margin-bottom: 15px;">Configure your <a href="{$smarty.const.WWW_TOP}/site-edit.php">site
					options</a>. The defaults will work fine.
			</li>
			<li style="margin-bottom: 15px;">There is a default list of usenet groups provided. To get started, you will
				need to <a href="{$smarty.const.WWW_TOP}/group-list.php">activate some groups</a>. <u>Do not</u>
				activate every group if its your first time setting this up. Try one or two first.
				You can also <a href="{$smarty.const.WWW_TOP}/group-edit.php">add your own groups</a> manually.
			</li>
			<li style="margin-bottom: 15px;">If you intend to keep using NNTmux, it is best to sign up for your own api
				keys from <a href="http://www.themoviedb.org/account/signup">TMDB</a> and <a
						href="http://aws.amazon.com/">Amazon</a>.
			</li>
		</ol>
	</div>
</div>
