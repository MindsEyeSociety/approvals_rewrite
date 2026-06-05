<?php
session_set_cookie_params( 0 );
session_name("approvals_2017");
session_start();
session_destroy();

$new_camnumber = isset($_POST['camnumber']) ? $_POST['camnumber'] :
                 (isset($_GET['camnumber']) ? $_GET['camnumber'] : null);

if( $new_camnumber ) {
	setcookie( 'dev_camnumber', $new_camnumber, -1, '/');
	setcookie( 'dev_camnumber', $new_camnumber, 0, "/" );
	$camnumber = $new_camnumber;
} elseif( isset( $_COOKIE['dev_camnumber'] )  ) {
	$camnumber = $_COOKIE['dev_camnumber'];	
} else {
	$camnumber = null;
}
?>
<html>
	<body>
		<h1>Login CAM Number:</h1>
		<form method="post">
			<input type="text" name="camnumber" id="camnumber" />
			<input type="submit" value="Login" />
		</form>
		<h2>Predefined users:</h2>
		<ul>
			<li><a href="index.php?camnumber=US2002020001">US2002020001 - Super User</a></li>
			<li><a href="index.php?camnumber=US2002020002">US2002020002 - Sample MST</a></li>
                        <li><a href="index.php?camnumber=US2002020003">US2002020003 - Sample NST</a></li>
                        <li><a href="index.php?camnumber=US2002020004">US2002020004 - Sample RST</a></li>
                        <li><a href="index.php?camnumber=US2002020005">US2002020005 - Sample DST</a></li>
                        <li><a href="index.php?camnumber=US2002020006">US2002020006 - Sample VST</a></li>
                        <li><a href="index.php?camnumber=US2002020007">US2002020007 - Sample General Member</a></li>
		</ul>
<?php if( $camnumber != null ) { ?>
		<div>Logged in as <?php echo $camnumber; ?></div>
		<div><a href="../index.php">Connect to Approvals DB</a></div>
<?php }?>
	</body>
</html>
