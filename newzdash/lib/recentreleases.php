<?php
use newznab\db\Settings;

class RecentReleases
{


	public function buildRecentTable($newznab_cat, $category)
	{
		printf('<div class="row-fluid">
				<div class="box span12">
				<div class="box-header well" data-original-title>
						<h2><i class="icon-fire"></i> %s</h2>
						<div class="box-icon">
							<a href="#" class="btn btn-minimize btn-round"><i class="icon-chevron-up"></i></a>
						</div>
					</div>
					<div class="box-content">', $category
		);

		echo '<table class="table table-striped table-bordered bootstrap-datatable datatable " >
							  <thead>
								  <tr>
									  <th style="display:none;">ordinal</th>
									  <th>Name</th>
									  <th>Category</th>
									  <th>Date (GMT)</th>
								  </tr>
							  </thead>
							  <tbody>';


		$category = new Category;
		# get all the child categories
		$allcategories = $category->getChildren($newznab_cat);

		$pdo = new Settings();

		$catarray = [];

		foreach ($allcategories as $cat) {
			array_push($catarray, $cat['id']);
		}

		$catstring = implode(',', $catarray);

		$sql = sprintf("select r.searchname as name, r.adddate as date, r.guid as guid, c.title as title from releases r inner join category c on c.id = r.categoryid where r.categoryid in (%s) order by r.adddate desc limit 0,50", $catstring);
		# print $sql;

		$res = $pdo->query($sql);
		$ordinal = 0;

		foreach ($res as $row) {
			$name = $row["name"];
			if (strlen($name) > 50) {
				$name = substr($row["name"], 0, 45);
				$name = $name . "...";
			}
			echo '<tr>';

			echo '<td style="display:none;">' . $ordinal . '</td>';
			$ordinal = $ordinal + 1;

			echo '<td>';
			echo '<a href="' . NEWZNAB_URL . '/details/' . $row["guid"] . '" class="btn btn-mini" target="_blank"><i class="icon-globe"></i></a> ' . $name;
			#echo '<a href="'.NEWZNAB_URL.'/details/'.$row["guid"].'">'.$row['name'].'</a>';
			echo '</td>';

			echo '<td>';
			echo $row['title'];
			echo '</td>';

			echo '<td>';
			echo $row["date"];
			echo '</td>';

			echo '</tr>';
		}


		echo '</tbody>
						 </table>  ';

		print '</div>
			       </div>
			       </div>';
	}

	public function buildRecentMoviesTable()
	{
		if (defined('SHOW_MOVIES') && SHOW_MOVIES === 'checked') {
			RecentReleases::buildRecentTable(Category::CAT_PARENT_MOVIE, "Movies");
		}
	}

	public function buildRecentMusicTable()
	{
		if (defined('SHOW_MUSIC') && SHOW_MUSIC === 'checked') {
			RecentReleases::buildRecentTable(Category::CAT_PARENT_MUSIC, "Music");
		}
	}

	public function buildRecentConsoleTable()
	{
		if (defined('SHOW_GAMES') && SHOW_GAMES === 'checked') {
			RecentReleases::buildRecentTable(Category::CAT_PARENT_GAME, "Console");
		}
	}

	public function buildRecentTVTable()
	{
		if (defined('SHOW_TV') && SHOW_TV === 'checked') {
			RecentReleases::buildRecentTable(Category::CAT_PARENT_TV, "Televison");
		}
	}

	public function buildRecentPCTable()
	{
		if (defined('SHOW_PC') && SHOW_PC === 'checked') {
			RecentReleases::buildRecentTable(Category::CAT_PARENT_PC, "PC");
		}
	}

	public function buildRecentXXXTable()
	{
		if (defined('SHOW_XXX') && SHOW_XXX === 'checked') {
			RecentReleases::buildRecentTable(Category::CAT_PARENT_XXX, "XXX");
		}
	}

}