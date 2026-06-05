<?php
include_once("db.inc");
include_once("application.inc");

$id = $_GET['id'];

$plotkitDAO = $daoFactory->getPlotKitDao();
$detailrow = $plotkitDAO->readByID($id);

$orgs = array();
$vsss = array();
$myvss_list = array();

if( is_array( $_SESSION['admin_final_authority_list'] && count($_SESSION['admin_final_authority_list']) > 0) ) {
	$orgDAO = $daoFactory->getOrgDAO();
	$admin_orgs = $orgDAO->readOrganizationsByIDs( $_SESSION['admin_final_authority_list'] );
	foreach( $admin_orgs as $row ) {
		$row["id"] = 0-$row["id"];
		$orgs[] = $row;
		$myvss_list[]=$row["id"];
	}
}

$vssDAO = $daoFactory->getVSSDAO();
$vsss = $vssDAO->readNamesBySTID( $_SESSION["user_id"] );
foreach( $vsss as $vss ) {
	$myvss_list[]=$vss["id"];
}

$mode=(isset($_GET['mode']))?$_GET['mode']:"display";

if( $mode == "Add" || ( $id == 0 || $id == "" ) ) {
	header("Location: PlotkitsMain.php?message=NoSuchPlotkit");
	exit();
}

$smarty->assign( "pagetitle", "Plotkit Details");
$smarty->assign( "vsss", $vsss );
$smarty->assign( "organizations", $orgs );
$smarty->assign( "plotkit", $detailrow );
$smarty->assign( "isOnMyVSS", in_array( $detailrow["vss_id"], $myvss_list ) );
$smarty->assign( "buttonValue", ($mode == "display")?"Edit Plotkit":"Enter Values" );
$smarty->assign( "canDelete", ( $detailrow["start_date"]>time() && $mode == "Edit") );
$smarty->assign( "mode", $mode );
$smarty->display( "PlotkitDetails.html");
