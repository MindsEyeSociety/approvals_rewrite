<?php
//include_once("db.inc");
include_once("classes/DAOFactory.php");
include_once("include/ResultSet.class.php");
include_once("include/Database.class.php");
include_once("include/settings.inc");
$db = new Database(
        $SETTINGS["APPROVALS_SERVER"],
        $SETTINGS["APPROVALS_USERNAME"],
        $SETTINGS["APPROVALS_PASSWORD"],
        $SETTINGS["APPROVALS_DATABASE"]
);
$daoFactory = DAOFactory::getInstance();
//include_once("application.inc");
//echo "got here";
//exit;
//include_once("classes/OrganizationService.class.php");
//include_once("classes/UserInfoService.class.php");
if(!function_exists('json_encode'))
{
	include_once('json.php');
	$GLOBALS['JSON_OBJECT'] = new Services_JSON();
	function json_encode($value)
	{
		return $GLOBALS['JSON_OBJECT']->encode($value); 
	}
	
	function json_decode($value)
	{
		return $GLOBALS['JSON_OBJECT']->decode($value); 
	}
}

$vssDAO = $daoFactory->getVSSDAO();
//$showall = isset($_GET["showall"])?($_GET["showall"]==true):false;
//$skip = isset($_GET["skip"])?intval($_GET["skip"]):0;
//$message = isset($_GET['message'])?$_GET["message"]:"";
$output = Array();

if($_REQUEST['vssId'] && ctype_digit($_REQUEST['vssId'])){
	$vss = $vssDAO->readVSSByID($_REQUEST['vssId']);
	$copyfields = Array('id' => 'vssId','name' => 'vssName','vss' => 'description');
	foreach($copyfields as $key => $field){
		if($vss[$key])
			$output[$field] = $vss[$key];
		else $output[$field] = null;
	}
}
else{
	$rows = $vssDAO->readVSSs( true, 0, $skip );
	$copyfields = Array('id' => 'vssId','modified' => 'lastModified', 'org_id' => 'organizationId','name' => 'vssName', 'email' => 'email', 'nation' => 'nation','region'=>'region',
	'domain' => 'domain','chapter'=>'chapter','city'=>'city','state'=>'state','country'=>'country','org_name'=>'organizationName','venue'=>'venue');
	foreach($rows as $row){
		$vss = Array();
		foreach($copyfields as $key =>$field){
			if($row[$key])
				$vss[$field] = $row[$key];
			else
				$vss[$field] = null;
		}
		$output[] = $vss;
	}

}
echo json_encode($output);
