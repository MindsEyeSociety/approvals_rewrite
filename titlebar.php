<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0d0d1a">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="anathema.css" type="text/css" rel="stylesheet" />
  <title><?php echo $pagetitle?></title>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="javascript/jquery.tooltip.js"></script>
</head>

<body>
<div id="layout">
  <button id="menu-toggle" aria-label="Open navigation menu" aria-expanded="false">&#9776;</button>
  <nav id="sidebar">
    <a href="http://www.modernenigmasociety.org/"><img style="border:0" src="images/mes.png" alt="Modern Enigma Society" /></a>
    <ul>
<?php
	foreach( $menus as $menu ) { ?>
		<li class="head" title="<?php echo $menu["tooltip"] ?>"><?php echo $menu["display"]?></li>
		<?php foreach( $menu["items"] as $item ) {?>
			<li title="<?php echo $item["tooltip"] ?>"><a href="<?php echo $item["url"] ?>"><?php echo $item["display"] ?></a></li>
<?php
		}
	}
?>
    </ul>
  </nav>
  <main id="content">
    <div id="header">
      <h1><?php echo $pagetitle?></h1>
    </div>
<script>
document.getElementById('menu-toggle').addEventListener('click', function() {
  var s = document.getElementById('sidebar');
  s.classList.toggle('open');
  this.setAttribute('aria-expanded', s.classList.contains('open'));
});
</script>
