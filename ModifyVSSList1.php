<?php
include_once("db.inc");
include_once("application.inc");
include_once("classes/OrganizationService.class.php");
include_once("classes/UserInfoService.class.php");

$vssDAO = $daoFactory->getVSSDAO();
$showall = isset($_GET["showall"])?($_GET["showall"]==true):false;
$skip = isset($_GET["skip"])?intval($_GET["skip"]):0;
$message = isset($_GET['message'])?$_GET["message"]:"";

$rows = $vssDAO->readVSSs( $showall, $_SESSION["org_id"], $skip );

$smarty->assign( "page_title", "Venue Listing" );
$smarty->assign( "message", $message );
$smarty->assign( "showall", $showall );

$last_org_id=0;
$counter = 0;
$vssData = array();
foreach( $rows as $row ) {
	$counter++;
	$row["editable"]=in_array($row["org_id"],$_SESSION["admin_org_list"]) || $_SESSION['super_user'];
	if ( $row["org_id"]!=$last_org_id ) {
		$last_org_id=$row["org_id"];
	  	if ( $row["chapter"]!="" ) {
			$row["org"] = $row["chapter"];
	  	} else if ( $row["domain"]!="" ) {
			$row["org"] = $row["domain"];
	  	} else if ( $row["region"]!="" ) {
			$row["org"] = $row["region"];
	  	} else if ( $row["nation"]!="" ) {
	  		$row["org"] = $row["nation"];
	  	} else {
	  		$row["org"] = "Global";
	  	}
	} else {
		$row["org"] = "";
	}
	$vssData[] = $row;
}

$smarty->assign( "vssData", $vssData );
$smarty->assign( "can_add", count( $_SESSION['admin_org_list'] ) > 0 );
	if( count($_SESSION['admin_org_list']) > 0 ) {
	$organizationDAO = $daoFactory->getOrganizationDAO();
	$organizationService = new OrganizationService( $organizationDAO );
	$organizations = $organizationService->getLabeledOrganizationTree( $_SESSION["admin_org_list"] );
	$smarty->assign("organizations", $organizations );

	$venueDAO = $daoFactory->getVenueDAO();
	$smarty->assign( "venues", $venueDAO->getVSSVenueOptions() );

	$userInfoService = new UserInfoService( $userInfoDAO );
	$smarty->assign( "storytellers", $userInfoService->getSortedOrganizationsUserList( $_SESSION['admin_org_list'] ) );
}

$smarty->display("ModifyVSSList1.html");