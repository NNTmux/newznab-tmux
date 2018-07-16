{if isset($covgroup)}
	{if $covgroup == "movies"}
        {{Form::open(['name' => 'browseby', 'url' => 'Movies', 'class' => 'form-inline'])}}
			<input class="form-control" style="width: 150px;" id="movietitle" type="text" name="title" value="{$title}"
				   placeholder="Title">
			<input class="form-control" style="width: 150px;" id="movieactors" type="text" name="actors"
				   value="{$actors}"
				   placeholder="Actor">
			<input class="form-control" style="width: 150px;" id="moviedirector" type="text" name="director"
				   value="{$director}" placeholder="Director">
			<select class="form-control" style="width: 150px;" id="rating" name="rating">
				<option class="grouping" value="">Rating...</option>
				{foreach $ratings as $rate}
					<option {if $rating == $rate}selected="selected"{/if} value="{$rate}">{$rate}</option>
				{/foreach}
			</select>
			<select class="form-control" style="width: 150px;" id="genre" name="genre" placeholder="Genre">
				<option class="grouping" value="">Genre...</option>
				{foreach $genres as $gen}
					<option {if $gen == $genre}selected="selected"{/if} value="{$gen}">{$gen}</option>
				{/foreach}
			</select>
			<select class="form-control" style="width: 150px;" id="year" name="year">
				<option class="grouping" value="">Year...</option>
				{foreach $years as $yr}
					<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
				{/foreach}
			</select>
			<select class="form-control" style="width: 150px;" id="category" name="t">
				<option class="grouping" value="{$catClass::MOVIE_ROOT}">Category...</option>
				{foreach $catlist as $ct}
					<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
				{/foreach}
			</select>
            {{Form::submit('Go', ['class' => 'btn btn-success'])}}
		{{Form::close()}}
	{/if}
	{if $covgroup == "xxx"}
        {{Form::open(['name' => 'browseby', 'url' => 'XXX', 'class' => 'form-inline'])}}
			<input class="form-control"
				   style="width: 150px;"
				   id="xxxtitle"
				   type="text"
				   name="title"
				   value="{$title}"
				   placeholder="Title">
			<input class="form-control"
				   style="width: 150px;"
				   id="xxxactors"
				   type="text"
				   name="actors"
				   value="{$actors}"
				   placeholder="Actor">
			<input class="form-control"
				   style="width: 150px;"
				   id="xxxdirector"
				   type="text"
				   name="director"
				   value="{$director}"
				   placeholder="Director">
			<select class="form-control"
					style="width: 150px;"
					id="genre"
					name="genre"
					placeholder="Genre">
				<option class="grouping" value="">Genre...</option>
				{foreach $genres as $gen}
					<option {if $gen == $genre}selected="selected"{/if} value="{$gen}">{$gen}</option>
				{/foreach}
			</select>
			<select class="form-control" style="width: 150px;" id="category" name="t">
				<option class="grouping" value="{$catClass::MOVIE_ROOT}">Category...</option>
				{foreach $catlist as $ct}
					<option {if $ct.id == $category}selected="selected"{/if}
							value="{$ct.id}">{$ct.title}</option>
				{/foreach}
			</select>
			<input class="btn btn-success" type="submit" value="Go">
		{{Form::close()}}
	{/if}
	{if $covgroup == "books"}
        {{Form::open(['name' => 'browseby', 'url' => 'Books', 'class' => 'form-inline'])}}
			<input class="form-control" style="width: 150px;" id="author" type="text" name="author" value="{$author}"
				   placeholder="Author">
			<input class="form-control" style="width: 150px;" id="title" type="text" name="title" value="{$title}"
				   placeholder="Title">
			<input class="btn btn-success" type="submit" value="Go">
		{{Form::close()}}
	{/if}
	{if $covgroup == "music"}
        {{Form::open(['name' => 'browseby', 'url' => 'Audio', 'class' => 'form-inline'])}}
			<input class="form-control" style="width: 150px;" id="musicartist" type="text" name="artist"
				   value="{$artist}"
				   placeholder="Artist">
			<input class="form-control" style="width: 150px;" id="musictitle" type="text" name="title" value="{$title}"
				   placeholder="Title">
			<select class="form-control" style="width: 150px;" id="genre" name="genre">
				<option class="grouping" value="">Genre...</option>
				{foreach $genres as $gen}
					<option {if $gen->id == $genre}selected="selected"{/if}
							value="{$gen->id}">{$gen->title|escape:"htmlall"}</option>
				{/foreach}
			</select>
			<select class="form-control" style="width: 150px;" id="year" name="year">
				<option class="grouping" value="">Year...</option>
				{foreach $years as $yr}
					<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
				{/foreach}
			</select>
			<select class="form-control" style="width: 150px;" id="category" name="t">
				<option class="grouping" value="{$catClass::MUSIC_ROOT}">Category...</option>
				{foreach $catlist as $ct}
					<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
				{/foreach}
			</select>
			<input class="btn btn-success" type="submit" value="Go">
		{{Form::close()}}
	{/if}
	{if $covgroup == "console"}
        {{Form::open(['name' => 'browseby', 'url' => 'Console', 'class' => 'form-inline'])}}
			<input class="form-control" style="width: 150px;" id="title" type="text" name="title" value="{$title}"
				   placeholder="Title">
			<input class="form-control" style="width: 150px;" id="platform" type="text" name="platform"
				   value="{$platform}"
				   placeholder="Platform">
			<select class="form-control" style="width: 150px;" id="genre" name="genre">
				<option class="grouping" value="">Genre...</option>
				{foreach $genres as $gen}
					<option {if $gen->id == $genre}selected="selected"{/if} value="{$gen->id}">{$gen->title}</option>
				{/foreach}
			</select>
			<select class="form-control" style="width: 150px;" id="category" name="t">
				<option class="grouping" value="{$catClass::GAME_ROOT}">Category...</option>
				{foreach $catlist as $ct}
					<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
				{/foreach}
			</select>
			<input class="btn btn-success" type="submit" value="Go">
		{{Form::close()}}
	{/if}
	{if $covgroup == "games"}
        {{Form::open(['name' => 'browseby', 'url' => 'Games', 'class' => 'form-inline'])}}
			<input class="form-control" style="width: 150px;" id="title" type="text" name="title" value="{$title}"
				   placeholder="Title">
			<select class="form-control" style="width: 150px;" id="genre" name="genre">
				<option class="grouping" value="">Genre...</option>
				{foreach $genres as $gen}
					<option {if $gen->id == $genre}selected="selected"{/if} value="{$gen->id}">{$gen->title}</option>
				{/foreach}
			</select>
			<select class="form-control" style="width: 150px;" id="year" name="year">
				<option class="grouping" value="">Year...</option>
				{foreach $years as $yr}
					<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
				{/foreach}
			</select>
			<input class="btn btn-success" type="submit" value="Go">
		{{Form::close()}}
	{/if}
{/if}
{if {$smarty.get.page} == "console"}
    {{Form::open(['name' => 'browseby', 'url' => 'Console', 'class' => 'form-inline'])}}
		<input class="form-control" style="width: 150px;" id="title" type="text" name="title" value="{$title}"
			   placeholder="Title">
		<input class="form-control" style="width: 150px;" id="platform" type="text" name="platform" value="{$platform}"
			   placeholder="Platform">
		<select class="form-control" style="width: auto;" id="genre" name="genre">
			<option class="grouping" value="">Genre...</option>
			{foreach $genres as $gen}
				<option {if $gen->id == $genre}selected="selected"{/if} value="{$gen->id}">{$gen->title}</option>
			{/foreach}
		</select>
		<select class="form-control" style="width: auto;" id="category" name="t">
			<option class="grouping" value="{$catClass::GAME_ROOT}">Category...</option>
			{foreach $catlist as $ct}
				<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
			{/foreach}
		</select>
		<input class="btn btn-success" type="submit" value="Go">
	{{Form::close()}}
{/if}
{if {$smarty.get.page} == "games"}
    {{Form::open(['name' => 'browseby', 'url' => 'Games', 'class' => 'form-inline'])}}
		<input class="form-control" style="width: 150px;" id="title" type="text" name="title" value="{$title}"
			   placeholder="Title">
		<select class="form-control" style="width: auto;" id="genre" name="genre">
			<option class="grouping" value="">Genre...</option>
			{foreach $genres as $gen}
				<option {if $gen->id == $genre}selected="selected"{/if} value="{$gen->id}">{$gen->title}</option>
			{/foreach}
		</select>
		<select class="form-control" style="width: auto;" id="year" name="year">
			<option class="grouping" value="">Year...</option>
			{foreach $years as $yr}
				<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
			{/foreach}
		</select>
		<input class="btn btn-success" type="submit" value="Go">
	{{Form::close()}}
{/if}
{if {$smarty.get.page} == "books"}
    {{Form::open(['name' => 'browseby', 'url' => 'Books', 'class' => 'form-inline'])}}
		<input class="form-control" style="width: 150px;" id="author" type="text" name="author" value="{$author}"
			   placeholder="Author">
		<input class="form-control" style="width: 150px;" id="title" type="text" name="title" value="{$title}"
			   placeholder="Title">
		<input class="btn btn-success" type="submit" value="Go">
	{{Form::close()}}
{/if}
{if {$smarty.get.page} == "movies"}
    {{Form::open(['name' => 'browseby', 'url' => 'Movies', 'class' => 'form-inline'])}}
		<input class="form-control" style="width: 150px;" id="movietitle" type="text" name="title" value="{$title}"
			   placeholder="Title">
		<input class="form-control" style="width: 150px;" id="movieactors" type="text" name="actors" value="{$actors}"
			   placeholder="Actor">
		<input class="form-control" style="width: 150px;" id="moviedirector" type="text" name="director"
			   value="{$director}" placeholder="Director">
		<select class="form-control" style="width: auto;" id="rating" name="rating">
			<option class="grouping" value="">Rating...</option>
			{foreach $ratings as $rate}
				<option {if $rating == $rate}selected="selected"{/if} value="{$rate}">{$rate}</option>
			{/foreach}
		</select>
		<select class="form-control" style="width: auto;" id="genre" name="genre" placeholder="Genre">
			<option class="grouping" value="">Genre...</option>
			{foreach $genres as $gen}
				<option {if $gen == $genre}selected="selected"{/if} value="{$gen}">{$gen}</option>
			{/foreach}
		</select>
		<select class="form-control" style="width: auto;" id="year" name="year">
			<option class="grouping" value="">Year...</option>
			{foreach $years as $yr}
				<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
			{/foreach}
		</select>
		<select class="form-control" style="width: auto;" id="category" name="t">
			<option class="grouping" value="{$catClass::MOVIE_ROOT}">Category...</option>
			{foreach $catlist as $ct}
				<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
			{/foreach}
		</select>
		<input class="btn btn-success" type="submit" value="Go">
	{{Form::close()}}
{/if}
{if {$smarty.get.page} == "xxx"}
    {{Form::open(['name' => 'browseby', 'url' => 'XXX', 'class' => 'form-inline'])}}
		<input class="form-control"
			   style="width: 150px;"
			   id="xxxtitle"
			   type="text"
			   name="title"
			   value="{$title}"
			   placeholder="Title">
		<input class="form-control"
			   style="width: 150px;"
			   id="xxxactors"
			   type="text"
			   name="actors"
			   value="{$actors}"
			   placeholder="Actor">
		<input class="form-control"
			   style="width: 150px;"
			   id="xxxdirector"
			   type="text"
			   name="director"
			   value="{$director}"
			   placeholder="Director">
		<select class="form-control"
				style="width: auto;"
				id="genre"
				name="genre"
				placeholder="Genre">
			<option class="grouping" value="">Genre...</option>
			{foreach $genres as $gen}
				<option {if $gen == $genre}selected="selected"{/if} value="{$gen}">{$gen}</option>
			{/foreach}
		</select>
		<select class="form-control" style="width: auto;" id="category" name="t">
			<option class="grouping" value="{$catClass::MOVIE_ROOT}">Category...</option>
			{foreach $catlist as $ct}
				<option {if $ct.id == $category}selected="selected"{/if}
						value="{$ct.id}">{$ct.title}</option>
			{/foreach}
		</select>
		<input class="btn btn-success" type="submit" value="Go">
	{{Form::close()}}
{/if}
{if {$smarty.get.page} == "music"}
    {{Form::open(['name' => 'browseby', 'url' => 'Audio', 'class' => 'form-inline'])}}
		<input class="form-control" style="width: 150px;" id="musicartist" type="text" name="artist" value="{$artist}"
			   placeholder="Artist">
		<input class="form-control" style="width: 150px;" id="musictitle" type="text" name="title" value="{$title}"
			   placeholder="Title">
		<select class="form-control" style="width: auto;" id="genre" name="genre">
			<option class="grouping" value="">Genre...</option>
			{foreach $genres as $gen}
				<option {if $gen->id == $genre}selected="selected"{/if}
						value="{$gen->id}">{$gen->title|escape:"htmlall"}</option>
			{/foreach}
		</select>
		<select class="form-control" style="width: auto;" id="year" name="year">
			<option class="grouping" value="">Year...</option>
			{foreach $years as $yr}
				<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
			{/foreach}
		</select>
		<select class="form-control" style="width: auto;" id="category" name="t">
			<option class="grouping" value="{$catClass::MUSIC_ROOT}">Category...</option>
			{foreach $catlist as $ct}
				<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
			{/foreach}
		</select>
		<input class="btn btn-success" type="submit" value="Go">
	{{Form::close()}}
{/if}
