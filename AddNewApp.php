<?php
	include_once("db.inc");
	include_once("application.inc");
        if(!$_SESSION['org_id']){
                header('Location: UserDisplay.php?mode=edit');
        }
	$thisuser_id=$_SESSION['user_id'];
	$isFromVSS = isset($_GET['from_vsss']);
	$isForOtherUser = isset( $_GET['appuser_id'] );
	$isStoryteller = count($_SESSION["admin_vss_list"]) > 0 || count($_SESSION["admin_org_list"]) > 0;

	$characterDAO = $daoFactory->getCharacterDAO();

	if ( $isForOtherUser && $userInfoDAO->isUserUnderOrganization( $_GET['appuser_id'], $_SESSION['admin_org_list'] ) ) {
		$thisuser_id=$_GET['appuser_id'];
	} else {
		$thisuser_id = $_SESSION["user_id"];
	}

	if ( $isFromVSS ) {
		$characters = $characterDAO->readByVSSs( $_SESSION['admin_vss_list']);
	} else {
		$characters = $characterDAO->readByUserID( $thisuser_id );
	}
	$smarty->assign("page_title", "Add New Application");
	$smarty->assign("user_id",$thisuser_id);
	$smarty->assign("characters", $characters );
	$smarty->assign("isFromVSS", $isFromVSS );
	$smarty->assign("isForOtherUser", $isForOtherUser );
	$smarty->assign("isStoryteller", $isStoryteller );
	$smarty->display("AddNewApp.html");
