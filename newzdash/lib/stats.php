<?php

use newznab\db\Settings;

class Stats
{

	public function buildReleaseTable()
	{
		if (!defined('SHOW_RPC') || SHOW_RPC != 'checked') {
			return;
		}

		print('<div class="row-fluid">
				<div class="box span12">
					<div class="box-header well" data-original-title>
						<h2><i class="icon-th"></i> Releases per Category</h2>
						<div class="box-icon">

							<a href="#" class="btn btn-minimize btn-round"><i class="icon-chevron-up"></i></a>
													</div>
					</div>
					<div class="box-content">');

		echo '<table class="table table-striped table-bordered bootstrap-datatable datatable">
							  <thead>
								  <tr>
									  <th>Category</th>
									  <th>Releases</th>
								  </tr>
							  </thead>
							  <tbody>';

		$category = new Category;
		# get all the active categories
		$allcategories = $category->get(true);

		$pdo = new Settings();

		foreach ($allcategories as $cat) {
			$sql = sprintf("select count(id) as num from releases where categoryid = %s", $cat['id']);
			$res = $pdo->queryOneRow($sql);

			if ($res["num"] > 0) {
				echo '<tr>';
				echo '<td>';
				echo $cat['title'];
				echo '</td>';

				echo '<td class="right">';
				echo $res["num"];
				echo '</td>';
				echo '</tr>';
			}
		}


		echo '</tbody>
						 </table>  ';

		print '</div></div></div>';
	}

	public function buildGroupTable()
	{
		if (!defined('SHOW_RPG') || SHOW_RPG != 'checked') {
			return;
		}

		print('<div class="row-fluid">
				<div class="box span12">
					<div class="box-header well" data-original-title>
						<h2><i class="icon-th"></i> Releases per Group</h2>
						<div class="box-icon">

							<a href="#" class="btn btn-minimize btn-round"><i class="icon-chevron-up"></i></a>
													</div>
					</div>
					<div class="box-content">');

		echo '<table class="table table-striped table-bordered bootstrap-datatable datatable">
							  <thead>
								  <tr>
									  <th>Group</th>
									  <th>Releases</th>
								  </tr>
							  </thead>
							  <tbody>';

		$group = new Groups;
		# get all the groups
		$allgroups = $group->getAll();

		$pdo = new Settings();


		foreach ($allgroups as $group) {
			$sql = sprintf("select count(id) as num from releases where groupid = %s", $group['id']);
			$res = $pdo->queryOneRow($sql);

			if ($res["num"] > 0) {
				echo '<tr>';
				echo '<td>';
				echo $group['name'];
				echo '</td>';

				echo '<td class="right">';
				echo $res["num"];
				echo '</td>';
				echo '</tr>';
			}

		}

		echo '</tbody>
						 </table>  ';

		print '</div></div></div>';
	}

	public function buildPendingTable()
	{
		if (!defined('SHOW_PROCESSING') || SHOW_PROCESSING != 'checked') {
			return;
		}

		print('<div class="row-fluid">
				<div class="box span12">
					<div class="box-header well" data-original-title>
						<h2><i class="icon-th"></i> To Be Processed</h2>
						<div class="box-icon">

							<a href="#" class="btn btn-minimize btn-round"><i class="icon-chevron-up"></i></a>
													</div>
					</div>
					<div class="box-content">');

		echo '<table class="table table-striped table-bordered ">
							  <thead>
								  <tr>
									  <th>Group</th>
									  <th>Pending</th>
								  </tr>
							  </thead>
							  <tbody>';

		$category = new Category;
		# get all the active categories
		$allcategories = $category->get(true);

		$pdo = new Settings();


		/////////////amount of books left to do//////
		$book_query = "select count(*) as todo from releases
					  		where (bookinfoid IS NULL AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0)
							and categoryid IN (7010, 7020, 7040, 7060);";
		/////////////amount of console left to do//////
		$console_query = "SELECT count(*) as todo from releases
							where (consoleinfoid IS NULL AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0)
							and categoryid in ( select id from category where parentid = 1000 AND categoryid != 1050);";
		/////////////amount of movies left to do//////
		$movies_query = "SELECT count(*) as todo from releases
							where (imdbid IS NULL AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0)
							and categoryid in ( select id from category where parentid = 2000 AND categoryid != 2020);";
		/////////////amount of music left to do//////
		$music_query = "SELECT count(*) as todo from releases
							where (musicinfoid IS NULL AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0)
							and categoryid in ( select id from category where parentid = 3000 AND categoryid != 3050);";
		/////////////amount of pc games left to do//////
		$pcgames_query = "SELECT count(*) as todo from releases
							where (gamesinfo_id = 0 AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0)
							and categoryid = 4050;";
		/////////////amount of tv left to do/////////
		$tvrage_query = "SELECT count(*) as todo from releases
							where (rageid = -1 AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0)
							and categoryid in ( select id from category where parentid = 5000  AND categoryid != 5050);";
		/////////////amount of XXX left to do/////////
		$xxx_query = "SELECT count(*) as todo from releases
							where (xxxinfo_id = 0 AND isrenamed = 0 AND proc_pp = 0 AND proc_par2 = 0 AND proc_nfo = 0 AND proc_files = 0 AND proc_sorter = 0)
							and categoryid in (select id from category where parentid = 6000 AND categoryid != 6070);";

		# books
		echo '<tr>';
		echo '<td>';
		echo 'Books';
		echo '</td>';
		echo '<td class="right">';
		$res = $pdo->queryOneRow($book_query);
		echo $res["todo"];
		echo '</td>';
		echo '</tr>';
		# games
		echo '<tr>';
		echo '<td>';
		echo 'Games';
		echo '</td>';
		echo '<td class="right">';
		$res = $pdo->queryOneRow($pcgames_query);
		echo $res["todo"];
		echo '</td>';
		echo '</tr>';
		# console
		echo '<tr>';
		echo '<td>';
		echo 'Console';
		echo '</td>';
		echo '<td class="right">';
		$res = $pdo->queryOneRow($console_query);
		echo $res["todo"];
		echo '</td>';
		echo '</tr>';
		# movies
		echo '<tr>';
		echo '<td>';
		echo 'Movies';
		echo '</td>';
		echo '<td class="right">';
		$res = $pdo->queryOneRow($movies_query);
		echo $res["todo"];
		echo '</td>';
		echo '</tr>';
		# music
		echo '<tr>';
		echo '<td>';
		echo 'Music';
		echo '</td>';
		echo '<td class="right">';
		$res = $pdo->queryOneRow($music_query);
		echo $res["todo"];
		echo '</td>';
		echo '</tr>';
		# tv
		echo '<tr>';
		echo '<td>';
		echo 'Television';
		echo '</td>';
		echo '<td class="right">';
		$res = $pdo->queryOneRow($tvrage_query);
		echo $res["todo"];
		echo '</td>';
		echo '</tr>';
		# XXX
		echo '<tr>';
		echo '<td>';
		echo 'XXX';
		echo '</td>';
		echo '<td class="right">';
		$res = $pdo->queryOneRow($xxx_query);
		echo $res["todo"];
		echo '</td>';
		echo '</tr>';


		echo '</tbody>
						 </table>  ';

		print '</div></div></div>';
	}
}