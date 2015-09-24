<?php

class ConfigForm
{

	public function getNewznabValues()
	{

		printf('<div class="control-group">
								<label class="control-label" for="focusedInput">Newznab-tmux Directory</label>
								<div class="controls">
								  <input class="input-xlarge" name="newznab_home" id="newznab_home" type="text" value="%s">
								</div>

							  </div>
							  <div class="control-group">
								<label class="control-label" for="focusedInput">Newznab-tmux URL</label>
								<div class="controls">
								  <input class="input-xlarge" name="newznab_url" id="newznab_url" type="text" value="%s">
								</div>
							  </div>', NEWZNAB_HOME, NEWZNAB_URL);

	}





        public function getRecentCheckboxes()
        {
		printf('<div class="control-group">
								<label class="control-label" for="optionsShowLatest">Show Recent...</label>
								<div class="controls">
								  <label class="checkbox">
									<input type="checkbox" name="show_movies" id="optionsShowLatest" %s value="checked" >
									Movies
								  </label>
								  <label class="checkbox">
									<input type="checkbox" name="show_tv" id="optionsShowLatest" %s value="checked" >
									Television
								  </label>
								  <label class="checkbox">
									<input type="checkbox" name="show_music" id="optionsShowLatest" %s value="checked" >
									Music
								  </label>
								  <label class="checkbox">
									<input type="checkbox" name="show_games" id="optionsShowLatest" %s value="checked" >
									Games
								  </label>
								  <label class="checkbox">
									<input type="checkbox" name="show_pc" id="optionsShowLatest" %s value="checked" >
									PC
								  </label>
								  <label class="checkbox">
									<input type="checkbox" name="show_other" id="optionsShowLatest" %s value="checked" >
									Other
								  </label>
								  <label class="checkbox">
									<input type="checkbox" name="show_xxx" id="optionsShowLatest" %s value="checked" >
									XXX
								  </label>
								</div>
							  </div>', SHOW_MOVIES, SHOW_TV, SHOW_MUSIC, SHOW_GAMES, SHOW_PC, SHOW_OTHER, SHOW_XXX);



        }

        public function getStatsCheckboxes()
        {
		printf('<div class="control-group">
								<label class="control-label" for="optionsShowLatest">Show Statistics...</label>
								<div class="controls">
								  <label class="checkbox">
									<input type="checkbox" name="show_processing" id="optionsShowLatest" %s value="checked" >
									To Be Processed
								  </label>
								  <label class="checkbox">
									<input type="checkbox" name="show_rpc" id="optionsShowLatest" %s value="checked" >
									Releases per Category
								  </label>
								  <label class="checkbox">
									<input type="checkbox" name="show_rpg" id="optionsShowLatest" %s value="checked" >
									Releases per Group
								  </label>

								</div>
							  </div>	', SHOW_PROCESSING, SHOW_RPC, SHOW_RPG);



        }
}






?>