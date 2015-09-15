<!DOCTYPE html>
<?php
require_once ('config.php');

if (!file_exists(NN_WWW . DS .'config.php')) {
	# send the browser to the configuration page, something is wrong!
	header("Location: configure.php");
}

require_once ('lib/stats.php');
$stats = new Stats;

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
						<a href="#">Statistics</a>
					</li>
				</ul>
			</div>
			<!-- breadcrumbs end -->
			<?php $stats->buildPendingTable(); ?>

			<?php $stats->buildReleaseTable(); ?>

			<?php $stats->buildGroupTable(); ?>
			<!-- content ends -->
		</div>
		<!--/#content.span10-->
	</div>
	<!--/fluid-row-->
	<hr>
	<div class="modal hide fade" id="myModal">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal">×</button>
			<h3>Settings</h3>
		</div>
		<div class="modal-body">
			<p>Here settings can be configured...</p>
		</div>
		<div class="modal-footer">
			<a href="#" class="btn" data-dismiss="modal">Close</a>
			<a href="#" class="btn btn-primary">Save changes</a>
		</div>
	</div>
	<?php include 'includes/bottombar.php'; ?>
</div>
<!--/.fluid-container-->
<!-- external javascript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->
</body>
</html>
