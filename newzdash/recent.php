<!DOCTYPE html>
<?php
require_once ('config.php');

if (!file_exists(NN_WWW . DS . 'config.php')) {
	# send the browser to the configuration page, something is wrong!
	header("Location: configure.php");
}

require_once ('lib/recentreleases.php');
$rr = new RecentReleases;
?>
<html lang="en">
<meta http-equiv="refresh" content="30">
<?php include 'includes/header.php'; ?>

<body>


<?php include 'includes/topbar.php'; ?>

<div class="container-fluid">
	<div class="row-fluid">

		<?php include('includes/leftmenu.php'); ?>

		<noscript>
			<div class="alert alert-block span10">
				<h4 class="alert-heading">Warning!</h4>

				<p>You need to have <a href="http://en.wikipedia.org/wiki/JavaScript" target="_blank">JavaScript</a>
					enabled to use this site.</p>
			</div>
		</noscript>

		<div id="content" class="span10">
			<!-- content starts -->

			<!-- breadcrumbs start -->
			<div>
				<ul class="breadcrumb">
					<li>
						<a href="/">Home</a> <span class="divider">/</span>
					</li>
					<li>
						<a href="#">Recent</a>
					</li>
				</ul>
			</div>
			<!-- breadcrumbs end -->


			<?php $rr->buildRecentMoviesTable(); ?>

			<?php $rr->buildRecentMusicTable(); ?>

			<?php $rr->buildRecentTVTable(); ?>

			<?php $rr->buildRecentConsoleTable(); ?>

			<?php $rr->buildRecentPCTable(); ?>

			<?php $rr->buildRecentXXXTable(); ?>


			<!-- content ends -->
		</div>
		<!--/#content.span10-->
	</div>
	<!--/fluid-row-->

	<hr>


	<?php include 'includes/bottombar.php'; ?>

</div>
<!--/.fluid-container-->


<!-- external javascript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->


</body>
</html>
