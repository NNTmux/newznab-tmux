<div class="well sell-sm">
	<h1>{$title}</h1>
	<p>
		Here you can choose rss feeds from settings categories. The feeds will present either descriptions or
		downloads of Nzb files.
	</p>
	<ul>
		<li>
			Add this string to your feed URL to allow NZB downloads without logging in: <span
					style="font-family:courier;">
				&amp;api_token={$userdata.api_token}</span>
		</li>
		<li>
			To remove the nzb from your cart after download add this string to your feed URL: <span
					style="font-family:courier;">&amp;del=1</span>
		</li>
		<li>
			To change the default link to download an nzb: <span style="font-family:courier;">&amp;dl=1</span>
		</li>
		<li>
			To change the number of results (default is 25, max is 100) returned: <span style="font-family:courier;">&amp;num=50</span>
		</li>
		<li>
			To return TV shows only aired in the last x days (default is all): <span style="font-family:courier;">&amp;airdate=20</span>
		</li>
	</ul>
	<p>
		Most Nzb clients which support Nzb rss feeds will appreciate the full URL, with download link and your user
		token.
	</p>
	<p>
		The feeds include additional attributes to help provide better filtering in your Nzb client, such as size, group
		and
		categorisation. If you want to chain multiple categories together or do more advanced searching, use the <a
				href="{{url("/apihelp")}}">api</a>, which returns its data in an rss compatible format.
	</p>
	<h2>Available Feeds</h2>
	<h3>General</h3>
	<ul style="text-align: left;">
		<li>
            <a href="{{url("/rss/full-feed?dl=1&amp;api_token={$userdata.api_token}")}}">Full site</a> feed </br>
            <a href="{{url("/rss/full-feed?dl=1&amp;api_token={$userdata.api_token}")}}">{{url("/rss/full-feed?dl=1&amp;api_token={$userdata.api_token}")}}</a>
            <br>You can define limit and num parameters, which will decide how much items to show and what offset to use (default values: limit 100 and offset 0).
        </li>
		<li>
			<a href="{{url("/cart/index")}}">My cart</a> feed<br/>
			<a href="{{url("/rss/cart?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}">{{url("/rss/cart?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}</a>
		</li>
		<li>
			<a href="{{url("/myshows")}}">My shows</a> feed<br/>
			<a href="{{url("/rss/myshows?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}">{{url("/rss/myshows?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}</a>
		</li>
		<li>
			<a href="{{url("/mymovies")}}">My movies</a> feed<br/>
			<a href="{{url("/rss/mymovies?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}">{{url("/rss/mymovies?dl=1&amp;api_token={$userdata.api_token}&amp;del=1")}}</a>
		</li>
	</ul>
	<h3>Parent Category</h3>
	<ul style="text-align: left;">
		{foreach $parentcategorylist as $category}
			<li>
				{$category.title} feed <br/>
				<a href="{{url("/rss/category?id={$category.id}&amp;dl=1&amp;api_token={$userdata.api_token}")}}">{{url("/rss/category?id={$category.id}&amp;dl=1&amp;api_token={$userdata.api_token}")}}"</a>
			</li>
		{/foreach}
	</ul>
	<h3>Sub Category</h3>
	<ul style="text-align: left;">
		{foreach $categorylist as $category}
            {if !empty($category.title)}
			    <li>
                    {$category.title} feed <br/>
				    <a href="{{url("/rss/category?id={$category.id}&amp;dl=1&amp;api_token={$userdata.api_token}")}}">{{url("/rss/category?id={$category.id}&amp;dl=1&amp;api_token={$userdata.api_token}")}}"</a>
			    </li>
            {/if}
		{/foreach}
	</ul>
	<h3>Multi Category</h3>
	<ul style="text-align: left;">
		<li>
			Multiple categories separated by comma.<br/>
			<a href="{{url("/rss/category?id={$catClass::MOVIE_ROOT},{$catClass::MUSIC_MP3}&amp;dl=1&amp;api_token={$userdata.api_token}")}}">{{url("/rss/category?id={$catClass::MOVIE_ROOT},{$catClass::MUSIC_MP3}&amp;dl=1&amp;api_token={$userdata.api_token}")}}</a>
		</li>
	</ul>
	<h2>Additional Feeds</h2>
	<ul style="text-align: left;">
		<li>
			Tv Series (Use the TVRage ID)<br/>
			<a href="{{url("/rss/full-feed?show={$show}&amp;dl=1&amp;api_token={$userdata.api_token}")}}">{{url("/rss/full-feedc?show={$show}&amp;dl=1&amp;api_token={$userdata.api_token}")}}</a>
		</li>
		<li>
			Tv Series aired in last seven days (Using the Video ID and airdate)<br/>
			<a href="{{url("/rss/full-feed?show={$show}&amp;airdate=7&amp;dl=1&amp;api_token={$userdata.api_token}")}}">{{url("/rss/full-feed?show={$show}&amp;airdate=7&amp;dl=1&amp;api_token={$userdata.api_token}")}}"</a>
		</li>
		<li>
			Anime Feed (Use the AniDB ID)<br/>
			<a href="{{url("/rss/full-feed?anidb={$anidb}&amp;dl=1&amp;api_token={$userdata.api_token}")}}">{{url("/rss/full-feed?anidb={$anidb}&amp;dl=1&amp;api_token={$userdata.api_token}")}}</a>
		</li>
	</ul>
</div>
