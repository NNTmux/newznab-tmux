
<?php
if (file_exists('config.php'))
{
  include('config.php');
}
else
{
  include('config.php.base');
}

require_once("lib/configform.php");

$configform = new ConfigForm;

?>

<html lang="en">
<?php include 'includes/header.php'; ?>

<body>
<?php include 'includes/topbar.php'; ?>

		<div class="container-fluid">
		<div class="row-fluid">

<?php include('includes/leftmenu.php'); ?>

			<noscript>
				<div class="alert alert-block span10">
					<h4 class="alert-heading">Warning!</h4>
					<p>You need to have <a href="http://en.wikipedia.org/wiki/JavaScript" target="_blank">JavaScript</a> enabled to use this site.</p>
				</div>
			</noscript>

			<div id="content" class="span10">
			<!-- content starts -->


			<div>
				<ul class="breadcrumb">
					<li>
						<a href="/">Home</a> <span class="divider">/</span>
					</li>
					<li>
						<a href="#">Configure</a>
					</li>
				</ul>
			</div>


			<div class="row-fluid sortable">
				<div class="box span12">
					<div class="box-header well" data-original-title>
						<h2><i class="icon-edit"></i> Configuration</h2>

					</div>
					<div class="box-content">
						<form class="form-horizontal" method="POST" name="config" id="config" action="" >
							<fieldset>
							  <?php $configform->getNewznabValues(); ?>

							  <?php $configform->getRecentCheckboxes(); ?>

							  <?php $configform->getStatsCheckboxes(); ?>


							  <div id="results"></div>
							  <div class="form-actions">
								<button type="submit" class="btn btn-primary">Save changes</button>
							  </div>
							</fieldset>
						  </form>

					</div>
				</div><!--/span-->

			</div><!--/row-->


					<!-- content ends -->
			</div><!--/#content.span10-->
				</div><!--/fluid-row-->

		<hr>

		<div id="results"><div>
	  <?php include 'includes/bottombar.php'; ?>

	</div><!--/.fluid-container-->

	<!-- external javascript
	================================================== -->
	<!-- Placed at the end of the document so the pages load faster -->


	<script src="js/jquery.validate.min.js"></script>
	<script type="text/javascript">
	$(document).ready(function(){
		$("#config").validate({
			debug: true,
			rules: {
				newznab_url: "required",
				newznab_home: "required"
			},
			messages: {
				newznab_url: "Please supply the url for newznab.",
				newznab_home: "Please supply the directory to where newznab is installed."
			},
			submitHandler: function(form) {
				// do other stuff for a valid form
				$.post('saveconfiguration.php', $("#config").serialize(), function(data) {
					$('#results').html(data);
				});
			}
		});
	});
	</script>

</body>
</html>