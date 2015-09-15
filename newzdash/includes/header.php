<!DOCTYPE html>
<!-- head starts -->
<head>
	<!--
		NewzDash v1.0.0

		Copyright 2013 Eric Young
		Licensed under the Apache License v2.0
		http://www.apache.org/licenses/LICENSE-2.0

		http://aceshome.com

		Based off original work titled 'Charisma' by Muhammad Usman
		   original license in doc/charisma-license.txt
	-->
	<meta charset="utf-8">
	<title>Newznab-tmux Dashboard</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Newznab, a usenet indexing web application with community features.">
	<meta name="author" content="DariusIII">

	<!-- The styles -->
	<link id="bs-css" href="../css/bootstrap-redy.css" rel="stylesheet">
	<style type="text/css">
	  body {
		padding-bottom: 40px;
	  }
	  .sidebar-nav {
		padding: 9px 0;
	  }
	</style>
	<link href="../css/bootstrap-responsive.css" rel="stylesheet">
	<link href="../css/charisma-app.css" rel="stylesheet">
	<link href="../css/jquery-ui-1.8.21.custom.css" rel="stylesheet">
	<link href='../css/fullcalendar.css' rel='stylesheet'>
	<link href='../css/fullcalendar.print.css' rel='stylesheet'  media='print'>
	<link href='../css/chosen.css' rel='stylesheet'>
	<link href='../css/uniform.default.css' rel='stylesheet'>
	<link href='../css/colorbox.css' rel='stylesheet'>
	<link href='../css/jquery.cleditor.css' rel='stylesheet'>
	<link href='../css/jquery.noty.css' rel='stylesheet'>
	<link href='../css/noty_theme_default.css' rel='stylesheet'>
	<link href='../css/elfinder.min.css' rel='stylesheet'>
	<link href='../css/elfinder.theme.css' rel='stylesheet'>
	<link href='../css/jquery.iphone.toggle.css' rel='stylesheet'>
	<link href='../css/opa-icons.css' rel='stylesheet'>
	<link href='../css/uploadify.css' rel='stylesheet'>

	<!-- The HTML5 shim, for IE6-8 support of HTML5 elements -->
	<!--[if lt IE 9]>
	  <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->

	<!-- The fav icon -->
	<link rel="shortcut icon" href="../img/favicon.ico">

		<!-- jQuery -->
	<script src="../js/jquery-1.7.2.min.js"></script>
	<!-- jQuery UI -->
	<script src="../js/jquery-ui-1.8.21.custom.min.js"></script>
	<!-- transition / effect library -->
	<script src="../js/bootstrap-transition.js"></script>
	<!-- alert enhancer library -->
	<script src="../js/bootstrap-alert.js"></script>
	<!-- modal / dialog library -->
	<script src="../js/bootstrap-modal.js"></script>
	<!-- custom dropdown library -->
	<script src="../js/bootstrap-dropdown.js"></script>
	<!-- scrolspy library -->
	<script src="../js/bootstrap-scrollspy.js"></script>
	<!-- library for creating tabs -->
	<script src="../js/bootstrap-tab.js"></script>
	<!-- library for advanced tooltip -->
	<script src="../js/bootstrap-tooltip.js"></script>
	<!-- popover effect library -->
	<script src="../js/bootstrap-popover.js"></script>
	<!-- button enhancer library -->
	<script src="../js/bootstrap-button.js"></script>
	<!-- accordion library (optional, not used in demo) -->
	<script src="../js/bootstrap-collapse.js"></script>
	<!-- carousel slideshow library (optional, not used in demo) -->
	<script src="../js/bootstrap-carousel.js"></script>
	<!-- autocomplete library -->
	<script src="../js/bootstrap-typeahead.js"></script>
	<!-- tour library -->
	<script src="../js/bootstrap-tour.js"></script>
	<!-- library for cookie management -->
	<script src="../js/jquery.cookie.js"></script>
	<!-- calander plugin -->
	<script src='../js/fullcalendar.min.js'></script>
	<!-- data table plugin -->
	<script src='../js/jquery.dataTables.min.js'></script>

	<!-- chart libraries start -->
	<script src="../js/excanvas.js"></script>
	<script src="../js/jquery.flot.min.js"></script>
	<script src="../js/jquery.flot.pie.min.js"></script>
	<script src="../js/jquery.flot.stack.js"></script>
	<script src="../js/jquery.flot.resize.min.js"></script>
	<!-- chart libraries end -->

	<!-- select or dropdown enhancer -->
	<script src="../js/jquery.chosen.min.js"></script>
	<!-- checkbox, radio, and file input styler -->
	<script src="../js/jquery.uniform.min.js"></script>
	<!-- plugin for gallery image view -->
	<script src="../js/jquery.colorbox.min.js"></script>
	<!-- rich text editor library -->
	<script src="../js/jquery.cleditor.min.js"></script>
	<!-- notification plugin -->
	<script src="../js/jquery.noty.js"></script>
	<!-- file manager library -->
	<script src="../js/jquery.elfinder.min.js"></script>
	<!-- star rating plugin -->
	<script src="../js/jquery.raty.min.js"></script>
	<!-- for iOS style toggle switch -->
	<script src="../js/jquery.iphone.toggle.js"></script>
	<!-- autogrowing textarea plugin -->
	<script src="../js/jquery.autogrow-textarea.js"></script>
	<!-- multiple file upload plugin -->
	<script src="../js/jquery.uploadify-3.1.min.js"></script>
	<!-- history.js for cross-browser state change on ajax -->
	<script src="../js/jquery.history.js"></script>
	<!-- application script for Charisma demo -->
	<script src="../js/charisma.js"></script>

</head>
<!-- head ends -->