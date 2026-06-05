<?php
include_once("db.inc");
include_once("application.inc");
$vssDAO = $daoFactory->getVSSDAO();
$orgDAO = $daoFactory->getOrganizationDAO();
$org_row = $orgDAO->readOrganizationsByIDs( $_SESSION["admin_final_authority_list"]);
$vss_row = $vssDAO->readVSSsByID( $_SESSION["admin_final_authority_vss_list"]);
/*
var_dump( $_SESSION["admin_final_authority_list"] );
print("<br />");
var_dump( $_SESSION["admin_final_authority_vss_list"] );
print("<br />");
var_dump( $vss_row );
print("<br />");
var_dump( $org_row );
print("<br />");
*/
if ( count($org_row) + count($vss_row) == 1 ) {
	if ( count($org_row)==1 ) {
		$org_id = $org_row[0]["id"];
		header("Location: ModifySTInstructions1.php?org_id=$org_id&");
		exit;
	} else {
	   $vss_id = $vss_row[0]["id"];
		header("Location: ModifySTInstructions1.php?vss_id=$vss_id&");
		exit;
	}
}

$smarty->assign( "page_title", "Set Storyteller Instructions" );
$smarty->assign( "org_row", $org_row );
$smarty->assign( "vss_row", $vss_row );

$smarty->display("ModifySTInstructions0.html");
