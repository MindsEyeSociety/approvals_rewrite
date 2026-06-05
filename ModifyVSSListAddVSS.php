<?php
include_once("db.inc");

$vss = new VSS(
	$_POST['name'],
	$_POST['email'],
	$_POST['venue_id'],
	$_POST['storyteller_id'],
	$_POST['org_id'],
	$_POST['vss']
);

// If the user is not a storyteller for an organization, they cannot add
// a VSS to it.
if( !in_array($vss->organization_id, $_SESSION['admin_org_list'] ) ) {
	header("Location: ModifyVSSList1.php?message=NotStoryteller");
}
if ( $vss->name != "") {
	$vssDAO = $daoFactory->getVSSDAO();
	$vssDAO->insert( $vss );
	header("Location: ModifyVSSList1.php");
} else {
	header("Location: ModifyVSSList1.php?message=NoName&");
}
