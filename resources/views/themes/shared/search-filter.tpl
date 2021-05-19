{if isset($covgroup)}
	<div class="row">
	{if $covgroup == "movies"}
        <div class="col">
        {{Form::open(['name' => 'browseby', 'url' => 'Movies', 'class' => 'form-inline', 'method' => 'get'])}}
			<input class="form-control form-control-sm" id="movietitle" type="text" name="title" value="{$title}"
				   placeholder="Title">
			<input class="form-control form-control-sm"  id="movieactors" type="text" name="actors"
				   value="{$actors}"
				   placeholder="Actor">
			<input class="form-control form-control-sm" id="moviedirector" type="text" name="director"
				   value="{$director}" placeholder="Director">
			<select class="form-control form-control-sm" id="rating" name="rating">
				<option class="grouping" value="">Rating...</option>
				{foreach $ratings as $rate}
					<option {if $rating == $rate}selected="selected"{/if} value="{$rate}">{$rate}</option>
				{/foreach}
			</select>
			<select class="form-control form-control-sm" id="genre" name="genre">
				<option class="grouping" value="">Genre...</option>
				{foreach $genres as $gen}
					<option {if $gen == $genre}selected="selected"{/if} value="{$gen}">{$gen}</option>
				{/foreach}
			</select>
			<select class="form-control form-control-sm" id="year" name="year">
				<option class="grouping" value="">Year...</option>
				{foreach $years as $yr}
					<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
				{/foreach}
			</select>
			<select class="form-control form-control-sm" id="category" name="t">
				<option class="grouping" value="{$catClass::MOVIE_ROOT}">Category...</option>
				{foreach $catlist as $ct}
					<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
				{/foreach}
			</select>
            {{Form::submit('Search', ['class' => 'btn btn-outline-success my-2 my-sm-0'])}}
		{{Form::close()}}
        </div>
	{/if}
	{if $covgroup == "xxx"}
        <div class="col">
        {{Form::open(['name' => 'browseby', 'url' => 'XXX', 'class' => 'form-inline', 'method' => 'get'])}}
			<input class="form-control form-control-sm"
				   style="width: 150px;"
				   id="xxxtitle"
				   type="text"
				   name="title"
				   value="{$title}"
				   placeholder="Title">
			<input class="form-control form-control-sm"
				   style="width: 150px;"
				   id="xxxactors"
				   type="text"
				   name="actors"
				   value="{$actors}"
				   placeholder="Actor">
			<input class="form-control form-control-sm"
				   style="width: 150px;"
				   id="xxxdirector"
				   type="text"
				   name="director"
				   value="{$director}"
				   placeholder="Director">
			<select class="form-control form-control-sm"
					style="width: 150px;"
					id="genre"
					name="genre"
					placeholder="Genre">
				<option class="grouping" value="">Genre...</option>
				{foreach $genres as $gen}
					<option {if $gen == $genre}selected="selected"{/if} value="{$gen}">{$gen}</option>
				{/foreach}
			</select>
			<select class="form-control form-control-sm" id="category" name="t">
				<option class="grouping" value="{$catClass::MOVIE_ROOT}">Category...</option>
				{foreach $catlist as $ct}
					<option {if $ct.id == $category}selected="selected"{/if}
							value="{$ct.id}">{$ct.title}</option>
				{/foreach}
			</select>
		{{Form::submit('Search', ['class' => 'btn btn-outline-success my-2 my-sm-0'])}}
		{{Form::close()}}
        </div>
	{/if}
	{if $covgroup == "books"}
        <div class="col">
        {{Form::open(['name' => 'browseby', 'url' => 'Books', 'class' => 'form-inline', 'method' => 'get'])}}
			<input class="form-control form-control-sm" id="author" type="text" name="author" value="{$author}"
				   placeholder="Author">
			<input class="form-control form-control-sm" id="title" type="text" name="title" value="{$title}"
				   placeholder="Title">
		{{Form::submit('Search', ['class' => 'btn btn-outline-success my-2 my-sm-0'])}}
		{{Form::close()}}
        </div>
	{/if}
	{if $covgroup == "music"}
        <div class="col">
        {{Form::open(['name' => 'browseby', 'url' => 'Audio', 'class' => 'form-inline'])}}
			<input class="form-control form-control-sm" id="musicartist" type="text" name="artist"
				   value="{$artist}"
				   placeholder="Artist">
			<input class="form-control form-control-sm" id="musictitle" type="text" name="title" value="{$title}"
				   placeholder="Title">
			<select class="form-control form-control-sm" id="genre" name="genre">
				<option class="grouping" value="">Genre...</option>
				{foreach $genres as $gen}
					<option {if $gen->id == $genre}selected="selected"{/if}
							value="{$gen->id}">{$gen->title|escape:"htmlall"}</option>
				{/foreach}
			</select>
			<select class="form-control form-control-sm" id="year" name="year">
				<option class="grouping" value="">Year...</option>
				{foreach $years as $yr}
					<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
				{/foreach}
			</select>
			<select class="form-control form-control-sm" id="category" name="t">
				<option class="grouping" value="{$catClass::MUSIC_ROOT}">Category...</option>
				{foreach $catlist as $ct}
					<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
				{/foreach}
			</select>
		{{Form::submit('Search', ['class' => 'btn btn-outline-success my-2 my-sm-0'])}}
		{{Form::close()}}
        </div>
	{/if}
	{if $covgroup == "console"}
        <div class="col">
        {{Form::open(['name' => 'browseby', 'url' => 'Console', 'class' => 'form-inline', 'method' => 'get'])}}
			<input class="form-control form-control-sm" id="title" type="text" name="title" value="{$title}"
				   placeholder="Title">
			<input class="form-control form-control-sm" id="platform" type="text" name="platform"
				   value="{$platform}"
				   placeholder="Platform">
			<select class="form-control form-control-sm" id="genre" name="genre">
				<option class="grouping" value="">Genre...</option>
				{foreach $genres as $gen}
					<option {if $gen->id == $genre}selected="selected"{/if} value="{$gen->id}">{$gen->title}</option>
				{/foreach}
			</select>
			<select class="form-control form-control-sm" id="category" name="t">
				<option class="grouping" value="{$catClass::GAME_ROOT}">Category...</option>
				{foreach $catlist as $ct}
					<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
				{/foreach}
			</select>
		{{Form::submit('Search', ['class' => 'btn btn-outline-success my-2 my-sm-0'])}}
		{{Form::close()}}
        </div>
	{/if}
	{if $covgroup == "games"}
        <div class="col">
        {{Form::open(['name' => 'browseby', 'url' => 'Games', 'class' => 'form-inline', 'method' => 'get'])}}
			<input class="form-control form-control-sm" id="title" type="text" name="title" value="{$title}"
				   placeholder="Title">
			<select class="form-control form-control-sm" id="genre" name="genre">
				<option class="grouping" value="">Genre...</option>
				{foreach $genres as $gen}
					<option {if $gen->id == $genre}selected="selected"{/if} value="{$gen->id}">{$gen->title}</option>
				{/foreach}
			</select>
			<select class="form-control form-control-sm" id="year" name="year">
				<option class="grouping" value="">Year...</option>
				{foreach $years as $yr}
					<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
				{/foreach}
			</select>
		{{Form::submit('Search', ['class' => 'btn btn-outline-success my-2 my-sm-0'])}}
		{{Form::close()}}
        </div>
	{/if}
{/if}
{if {$smarty.get.page} == "console"}
    <div class="col">
    {{Form::open(['name' => 'browseby', 'url' => 'Console', 'class' => 'form-inline', 'method' => 'get'])}}
		<input class="form-control form-control-sm" id="title" type="text" name="title" value="{$title}"
			   placeholder="Title">
		<input class="form-control form-control-sm" id="platform" type="text" name="platform" value="{$platform}"
			   placeholder="Platform">
		<select class="form-control form-control-sm" style="width: auto;" id="genre" name="genre">
			<option class="grouping" value="">Genre...</option>
			{foreach $genres as $gen}
				<option {if $gen->id == $genre}selected="selected"{/if} value="{$gen->id}">{$gen->title}</option>
			{/foreach}
		</select>
		<select class="form-control form-control-sm" style="width: auto;" id="category" name="t">
			<option class="grouping" value="{$catClass::GAME_ROOT}">Category...</option>
			{foreach $catlist as $ct}
				<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
			{/foreach}
		</select>
	{{Form::submit('Search', ['class' => 'btn btn-outline-success my-2 my-sm-0'])}}
	{{Form::close()}}
    </div>
{/if}
{if {$smarty.get.page} == "games"}
    <div class="col">
    {{Form::open(['name' => 'browseby', 'url' => 'Games', 'class' => 'form-inline', 'method' => 'get'])}}
		<input class="form-control form-control-sm" id="title" type="text" name="title" value="{$title}"
			   placeholder="Title">
		<select class="form-control form-control-sm" style="width: auto;" id="genre" name="genre">
			<option class="grouping" value="">Genre...</option>
			{foreach $genres as $gen}
				<option {if $gen->id == $genre}selected="selected"{/if} value="{$gen->id}">{$gen->title}</option>
			{/foreach}
		</select>
		<select class="form-control form-control-sm" style="width: auto;" id="year" name="year">
			<option class="grouping" value="">Year...</option>
			{foreach $years as $yr}
				<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
			{/foreach}
		</select>
	{{Form::submit('Search', ['class' => 'btn btn-outline-success my-2 my-sm-0'])}}
	{{Form::close()}}
    </div>
{/if}
{if {$smarty.get.page} == "books"}
    <div class="col">
    {{Form::open(['name' => 'browseby', 'url' => 'Books', 'class' => 'form-inline', 'method' => 'get'])}}
		<input class="form-control form-control-sm" id="author" type="text" name="author" value="{$author}"
			   placeholder="Author">
		<input class="form-control form-control-sm" id="title" type="text" name="title" value="{$title}"
			   placeholder="Title">
	{{Form::submit('Search', ['class' => 'btn btn-outline-success my-2 my-sm-0'])}}
	{{Form::close()}}
    </div>
{/if}
{if {$smarty.get.page} == "movies"}
    <div class="col">
    {{Form::open(['name' => 'browseby', 'url' => 'Movies', 'class' => 'form-inline'])}}
		<input class="form-control form-control-sm" id="movietitle" type="text" name="title" value="{$title}"
			   placeholder="Title">
		<input class="form-control form-control-sm" id="movieactors" type="text" name="actors" value="{$actors}"
			   placeholder="Actor">
		<input class="form-control form-control-sm" id="moviedirector" type="text" name="director"
			   value="{$director}" placeholder="Director">
		<select class="form-control form-control-sm" style="width: auto;" id="rating" name="rating">
			<option class="grouping" value="">Rating...</option>
			{foreach $ratings as $rate}
				<option {if $rating == $rate}selected="selected"{/if} value="{$rate}">{$rate}</option>
			{/foreach}
		</select>
		<select class="form-control form-control-sm" style="width: auto;" id="genre" name="genre" placeholder="Genre">
			<option class="grouping" value="">Genre...</option>
			{foreach $genres as $gen}
				<option {if $gen == $genre}selected="selected"{/if} value="{$gen}">{$gen}</option>
			{/foreach}
		</select>
		<select class="form-control form-control-sm" style="width: auto;" id="year" name="year">
			<option class="grouping" value="">Year...</option>
			{foreach $years as $yr}
				<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
			{/foreach}
		</select>
		<select class="form-control form-control-sm" style="width: auto;" id="category" name="t">
			<option class="grouping" value="{$catClass::MOVIE_ROOT}">Category...</option>
			{foreach $catlist as $ct}
				<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
			{/foreach}
		</select>
	{{Form::submit('Search', ['class' => 'btn btn-outline-success my-2 my-sm-0'])}}
	{{Form::close()}}
    </div>
{/if}
{if {$smarty.get.page} == "xxx"}
    <div class="col">
    {{Form::open(['name' => 'browseby', 'url' => 'XXX', 'class' => 'form-inline', 'method' => 'get'])}}
		<input class="form-control form-control-sm"
			   style="width: 150px;"
			   id="xxxtitle"
			   type="text"
			   name="title"
			   value="{$title}"
			   placeholder="Title">
		<input class="form-control form-control-sm"
			   style="width: 150px;"
			   id="xxxactors"
			   type="text"
			   name="actors"
			   value="{$actors}"
			   placeholder="Actor">
		<input class="form-control form-control-sm"
			   style="width: 150px;"
			   id="xxxdirector"
			   type="text"
			   name="director"
			   value="{$director}"
			   placeholder="Director">
		<select class="form-control form-control-sm"
				style="width: auto;"
				id="genre"
				name="genre"
				placeholder="Genre">
			<option class="grouping" value="">Genre...</option>
			{foreach $genres as $gen}
				<option {if $gen == $genre}selected="selected"{/if} value="{$gen}">{$gen}</option>
			{/foreach}
		</select>
		<select class="form-control form-control-sm" style="width: auto;" id="category" name="t">
			<option class="grouping" value="{$catClass::MOVIE_ROOT}">Category...</option>
			{foreach $catlist as $ct}
				<option {if $ct.id == $category}selected="selected"{/if}
						value="{$ct.id}">{$ct.title}</option>
			{/foreach}
		</select>
	{{Form::submit('Search', ['class' => 'btn btn-outline-success my-2 my-sm-0'])}}
	{{Form::close()}}
    </div>
{/if}
{if {$smarty.get.page} == "music"}
        <div class="col">
    {{Form::open(['name' => 'browseby', 'url' => 'Audio', 'class' => 'form-inline', 'method' => 'get'])}}
		<input class="form-control form-control-sm" id="musicartist" type="text" name="artist" value="{$artist}"
			   placeholder="Artist">
		<input class="form-control form-control-sm" id="musictitle" type="text" name="title" value="{$title}"
			   placeholder="Title">
		<select class="form-control form-control-sm" style="width: auto;" id="genre" name="genre">
			<option class="grouping" value="">Genre...</option>
			{foreach $genres as $gen}
				<option {if $gen->id == $genre}selected="selected"{/if}
						value="{$gen->id}">{$gen->title|escape:"htmlall"}</option>
			{/foreach}
		</select>
		<select class="form-control form-control-sm" style="width: auto;" id="year" name="year">
			<option class="grouping" value="">Year...</option>
			{foreach $years as $yr}
				<option {if $yr == $year}selected="selected"{/if} value="{$yr}">{$yr}</option>
			{/foreach}
		</select>
		<select class="form-control form-control-sm" style="width: auto;" id="category" name="t">
			<option class="grouping" value="{$catClass::MUSIC_ROOT}">Category...</option>
			{foreach $catlist as $ct}
				<option {if $ct.id == $category}selected="selected"{/if} value="{$ct.id}">{$ct.title}</option>
			{/foreach}
		</select>
		<input class="btn btn-outline-success my-2 my-sm-0" type="submit" value="Search">
	{{Form::close()}}
        </div>
	</div>
{/if}
