<?php
include_once("db.inc");
include_once("classes/OrganizationService.class.php");
include_once("classes/UserInfoService.class.php");
include_once("application.inc");
$vss_id = intval( $_GET['modify'] );
$message = isset($_GET["message"]) ? $_GET["message"] : "";


$vssDAO = $daoFactory->getVSSDAO();
$organizationDAO = $daoFactory->getOrganizationDAO();
$organizationService = new OrganizationService( $organizationDAO );
$venueDAO = $daoFactory->getVenueDAO();
$userInfoDAO = $daoFactory->getUserInfoDAO();
$userInfoService = new UserInfoService( $userInfoDAO );
$vss = $vssDAO->readVSSById( $vss_id );
$smarty->assign( "page_title", "Modify VSS Information" );
$smarty->assign( "message", $message );
$smarty->assign( "vss_id", $vss_id );
$smarty->assign( "vss", $vss );
$smarty->assign( "organizations", $organizationService->getLabeledOrganizationTree( $_SESSION["admin_org_list"] ) );
$smarty->assign( "venues", $venueDAO->getVSSVenueOptions() );
$smarty->assign( "storytellers", $userInfoService->getSortedOrganizationsUserList( $_SESSION["admin_org_list"], $vss['storyteller_id'] ) );
$smarty->display( "ModifyVSSList3.html" );
