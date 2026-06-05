<?php
//include_once("db.inc");
//include_once("classes/DAOFactory.php");
include_once("include/Database.class.php");
$db = new Database(
        $SETTINGS["APPROVALS_SERVER"],
        $SETTINGS["APPROVALS_USERNAME"],
        $SETTINGS["APPROVALS_PASSWORD"],
        $SETTINGS["APPROVALS_DATABASE"]
);
//$daoFactory = DAOFactory::getInstance();
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

//$orgDAO = $daoFactory->getOrganizationDAO();
//$showall = isset($_GET["showall"])?($_GET["showall"]==true):false;
//$skip = isset($_GET["skip"])?intval($_GET["skip"]):0;
//$message = isset($_GET['message'])?$_GET["message"]:"";
$output = Array();

	//$rows = $orgDAO->readLocalOrganizations();
	$rows = $db->query("SELECT * FROM organizations ORDER BY org_name")->getAllRows();
	$copyfields = Array('id' => 'organizationId','org_name' => 'organizationName', 'nation' => 'nation','region'=>'region',
	'domain' => 'domain','chapter'=>'chapter','city'=>'city','state'=>'state','country'=>'country', 'email' => 'email', 'email_is_google' => 'emailIsGoogle');
	foreach($rows as $row){
		$org = Array();
		foreach($copyfields as $key =>$field){
			if($row[$key])
				$org[$field] = $row[$key];
			else
				$org[$field] = null;
		}
		$output[] = $org;
	}
echo json_encode($output);
