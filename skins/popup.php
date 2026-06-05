<?php
class PopupPage {
	var $pagetitle;

	function showHeader( ) {
?>
<!DOCTYPE html>
<html>
<head>
	<link href="anathema.css" type='text/css' rel='stylesheet'>
	<title><?php echo $this->pagetitle?></title>
	<script type="text/javascript" src="https://code.jquery.com/jquery-1.7.2.min.js"></script>
	<script type="text/javascript" src="javascript/jquery.tooltip.js"></script>
</head>

<body>
<h1><?php echo $this->pagetitle?></h1>
<?php
	}

	function showFooter() {
?>
<div id="footer">
<div align="center" class="normalsmall"><a href="http://legacy.modernenigmasociety.org/bugs/">Enter Feedback or Bug Report for this page</a></div>
<?php
	echo activateUserPopups();
?>
</div>
</body>
</html>
<?php
	}
}
?>
