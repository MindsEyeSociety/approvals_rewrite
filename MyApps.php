<?php
include_once("db.inc");
include_once("application.inc");

$applicationDAO = $daoFactory->getApplicationDAO();
$rows = $applicationDAO->readMyAppRowData( $_SESSION['user_id'], !isset( $_GET['showall'] ) || $_GET['showall'] == "" );

$applications = array();
foreach( $rows as $row ) {
	if ( $row["status"]!="Approved" && $row["status"]!="Denied" && $row["status"]!="Removed" ) {
		$applications[] = $row;
	}
}

$userInfoDAO = $daoFactory->getUserInfoDAO();
$swap_ids = $userInfoDAO->readIDsByCamNumber( $_SESSION['cam_number'] );

$smarty->assign( "page_title", "My Approvals" );
$smarty->assign( "menus", generateMenus() );
$smarty->assign( "applications", $applications );
$smarty->assign( "show_all", array_key_exists( 'showall', $_GET ) ? $_GET['showall'] : "" );
$smarty->assign( "swap_ids", $swap_ids );
$smarty->display( "MyApps.html" );
