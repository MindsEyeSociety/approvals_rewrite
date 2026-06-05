<?php
include_once("db.inc");
include_once("application.inc");

$plotkitDAO = $daoFactory->getPlotkitDAO();
$plotkits = $plotkitDAO->readAll(isset( $_GET['show_time_all'] ));

if ( isset($_GET['show_time_all']) ) {
	$smarty->assign( "show_hide_url", "PlotkitsMain.php" );
	$smarty->assign( "show_hide_message", "Show only current plotkits" );
	$smarty->assign( "page_title", "Current, Past and Future Plotkits" );
} else {
	$smarty->assign( "show_hide_url", "PlotkitsMain.php?show_time_all=1" );
	$smarty->assign( "show_hide_message", "Show current, past and future plotkits" );
	$smarty->assign( "page_title", "Current Plotkits" );
}

$smarty->assign( "plotkits", $plotkits );
$smarty->display( "PlotkitsMain.html");
