<?php
include_once("db.inc");
include_once("application.inc");

// TODO: Replace this with some XSS protection
$redirect = $_GET["redirect"] ?? "";
$errmsg = isset( $_GET["errmsg"] ) ? $_GET["errmsg"] : 0;

$venueDAO = $daoFactory->getVenueDAO();
$characterDAO = $daoFactory->getCharacterDAO();

$smarty->assign("page_title","Character List");
$smarty->assign("redirect",$redirect);

$venues = $venueDAO->getVenueOptions();
$smarty->assign("venues", $venues);

// Get subtypes for all venues
$subtypes = array();
foreach ($venues as $venue) {
    $subtypes[$venue['id']] = $characterDAO->getSubtypesForVenue($venue['id']);
}
$smarty->assign("subtypes", $subtypes);

// Output JavaScript for subtypes
$js_subtypes = json_encode($subtypes);
$smarty->assign("js_subtypes", $js_subtypes);

if( $errmsg == 1 ) {
	$smarty->assign("message", "DuplicateName");
} else if ( $errmsg == 2 ) {
	$smarty->assign("message", "BlankName");
}

if ( isset( $_GET['appuser_id'] ) && $userInfoDAO->isUserUnderOrganization( $_GET['appuser_id'], $_SESSION["admin_org_list"] ) ) {
	$smarty->assign("user_id", $_GET["appuser_id"] );
} else {
	$smarty->assign('user_id',$_SESSION['user_id']);
}

$smarty->display("AddCharacter.html");
