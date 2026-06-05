<?php
include_once("db.inc");

// Setup template object
$app_root = $_SERVER['DOCUMENT_ROOT'] . "/approvals_2017";
ini_set('include_path', ini_get("include_path") . PATH_SEPARATOR . "/var/www/html/" . PATH_SEPARATOR . "/var/www/html/crd/.global/.classes/.smarty/".PATH_SEPARATOR."$app_root/include");

include_once( "application.inc" );

if ( !isset( $_SESSION['user_id'] ) && !isset( $IGNORE_LOGIN ) ) {
	exit();
}

$smarty->assign('page_title', "Submit Plotkit");
$smarty->assign('admin_org', count($_SESSION["admin_org_list"]) > 0 ? 'true' : 'false');
$smarty->assign('admin_vss', count($_SESSION["admin_vss_list"]) > 0 ? 'true' : 'false');
$smarty->assign('super_user', $_SESSION["super_user"] ? 'true' : 'false');

//check security to be sure I can do this
if ( count($_SESSION["admin_org_list"]) ==  0 && count( $_SESSION["admin_vss_list"] ) == 0) {
	header("location: PlotkitsMain.php");
	exit();
}

//if we are first visiting this page display form
if(!isset($_POST['submit']))
{
	$vssDAO = $daoFactory->getVSSDAO();
	$rows = $vssDAO->readVSSsByID($_SESSION['admin_vss_list']);
	$vsss = array();
	foreach( $rows as $row ) {
		$vsss[$row['id']] = $row['name'];
	}

	$smarty->assign("vsss", $vsss);
	$smarty->display("PlotkitsSubmit.html");
	exit();
}

//here is the stuff we do when posting
$plotkit = new Plotkit(
	$_POST['vss_id'],
	$_POST['title'],
	"$_POST[start_Year]-$_POST[start_Month]-$_POST[start_Day]",
	"$_POST[end_Year]-$_POST[end_Month]-$_POST[end_Day]",
	$_SESSION['user_id'],
	$_POST['url'],
	$_POST['plot_details']
);

if( $plotkit->vss_id == '' or
	$plotkit->title == '' or
	$plotkit->storyteller_id == ''or
	$plotkit->url == ''or
	$plotkit->plot_details == '') {
	$smarty->assign('content_string', "Error, click your back button and make sure all fields are filled out.");
	$smarty->display("frame.tpl"); exit;
}

$vssDAO = $daoFactory->getVSSDAO();
$plotkit->venue_id = $vssDAO->readVenueID($plotkit->vss_id);

//just insert!
$plotkitDAO = $daoFactory->getPlotkitDAO();
$plotkitDAO->insert( $plotkit );

header("Location: PlotkitsMain.php");
