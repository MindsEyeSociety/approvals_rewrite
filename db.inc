<?php

ini_set('date.timezone','America/Los_Angeles');
ini_set( "session.gc_maxlifetime", 3600 );
	if (session_status() === PHP_SESSION_NONE) {
		session_set_cookie_params([
			'lifetime' => 0,
			'path' => '/',
			'domain' => '.modernenigmasociety.org',
			'secure' => true,
			'httponly' => true,
			'samesite' => 'Lax'
		]);
		session_name("approvals2017");
		session_start();
	}

include_once("classes/Singleton.php");
include_once("classes/UserInfoDAO.php");
include_once("classes/DAOFactory.php");

header( "Pragma: no-cache");
header( "Cache-control: no-cache" );
header( "Expires: -1" );

include_once("include/ResultSet.class.php");
include_once("include/Database.class.php");

function userValidForNumber( $id, $camnum ) {
	global $db;

	if( !is_numeric( $id ) ) {
		die("INCORRECT USER_ID FOR userValidForNumber FUNCTION - ".$id);
	}
	$query="SELECT u.ww_number, u.id ".
					 "FROM users u	".
					 "WHERE u.id=$id AND u.ww_number='$camnum'";
	$res = $db->query("$query" );
	if ($res->numRows() > 0) {
		return true;
	} else {
		return false;
	}
}

include_once("include/settings.inc");
$db = new Database(
	$SETTINGS["APPROVALS_SERVER"],
	$SETTINGS["APPROVALS_USERNAME"],
	$SETTINGS["APPROVALS_PASSWORD"],
	$SETTINGS["APPROVALS_DATABASE"]
);
$portal_db = new Database(
	$SETTINGS["PORTAL_SERVER"],
	$SETTINGS["PORTAL_USERNAME"],
	$SETTINGS["PORTAL_PASSWORD"],
	$SETTINGS["PORTAL_DATABASE"]
);

$daoFactory = DAOFactory::getInstance();
$userInfoDAO = $daoFactory->getUserInfoDAO();

if (isset($_SESSION['user_id'])) {
    include_once("session_setup.inc");
}

function getUserID( $cam_number ) {
	global $db;

        // Clean off the temporary digit, so that temp users don't end up with 
        // two accounts
	//$cam_number = ereg_replace("T$","",$cam_number);
	$cam_number = preg_replace("/T$/", "", $cam_number);
	if (strlen(trim($cam_number)) <= 0) {
		print("Something went wrong. Your cam number is empty. Please be sure you have <a href=\"http://portal.moernenigmasociety.org/membership/claim.php\">claimed your membership number</a>."); 
		exit();
	}

	// Try to push the cam number into the US2002021697 format without and dashes, spaces, etc.
	//$clean_num = ereg_replace("-","",$cam_number);
	$clean_num = str_replace("-", "", $cam_number);
	//$clean_num = ereg_replace(" ","",$clean_num);
	$clean_num = str_replace(" ", "", $clean_num);

	$query="SELECT id ".
		"FROM users ".
		"WHERE ww_number='%s' OR ww_number='%s'";

	$res = $db->query( sprintf( $query,
		$db->escape($cam_number),
		$db->escape($clean_num)
	) );
	if ($res->numRows() > 0) {
		$row = $res->nextRow();
		$user_id = $row['id'];
	} else {
		$query = "INSERT INTO users (ww_number, username) VALUES ('%s','%s')";
		$res = $db->query( sprintf( $query,
			$db->escape( $cam_number ),
			$db->escape( $cam_number )
		) );
		$user_id = getUserID($cam_number);
	}

	return $user_id;
}

function userInfoPopup( $user_id ) {
	$daoFactory = DAOFactory::getInstance();
	$userInfoDAO = $daoFactory->getUserInfoDAO();
	if( !is_null($user_id) && $user_id != 0 ) {
		$user_info = $userInfoDAO->getUserInfo( $user_id );
		return "<a href=\"mailto:$user_info[email]\" class=\"userLink\" userid=\"{$user_id}\">$user_info[name]</a>";
	} else {
		return "<i>(none)</i>";
	}
}

function activateUserPopups() {
	$daoFactory = DAOFactory::getInstance();
	$userInfoDAO = $daoFactory->getUserInfoDAO();
	$userIDs = $userInfoDAO->getCachedIDs();
	$o="<script type=\"text/javascript\">\n\$(function(){\n";
	$o.="\tvar USER_TOOLTIPS = new Array();\n";
	foreach( $userIDs as $userID ) {
		$userInfo = $userInfoDAO->getUserInfo($userID);
		$positions = $userInfoDAO->getPositions($userID);

		$popupString = "<div>{$userInfo["name"]}</div>";
		if( count( $positions ) > 0 ) {
			$popupString .= "<div class=\"positions\">";
			foreach( $positions as $position ) {
				$popupString .= "<div>$position</div>";
			}
			$popupString .= "</div>";
		}
		$popupString = str_replace("\"","\\\"",$popupString);

		$o .= "\tUSER_TOOLTIPS[{$userID}] = \"{$popupString}\";\n";
	}
	$o .= "\t\$('a.userLink').tooltip({showURL:false,delay:0,track:true,bodyHandler:function(){return USER_TOOLTIPS[$(this).attr('userid')]}});\n";
	$o .="});\n</script>";
	return $o;
}

// Formats random free text that users enter into something vageuly readable
// Accounting for HTML vagaries and without using the accursed <pre> tag.
function formatBlockText( $text ) {
	$formattedText = nl2br( $text );
	$formattedText = str_replace("	", "&nbsp;&nbsp;", $formattedText );
	$formattedText = str_replace(".&nbsp;", ". ", $formattedText);
	$formattedText = str_replace(chr(9),"&nbsp;&nbsp;&nbsp;&nbsp; ", $formattedText);
	$formattedText = str_replace(",&nbsp;", ", ", $formattedText);
	return "<span class=\"cs\">".$formattedText."</span>";
}

function email_is_google($email){
	if(!$email) return 0;
	list(,$hostname) = explode("@",$email);
	if($hostname == 'gmail.com') return 1;
	$mxhosts = Array();
	getmxrr($hostname,$mxhosts);
	if(!$mxhosts[0]) return 0;
	$hostparts = explode('.',$mxhosts[0]);
	$hostpartcount = count($hostparts);
	if($hostpartcount < 2) return 0;
	$hostname = $hostparts[$hostpartcount-2];
	if($hostname=='google' || $hostname=='googlemail') return 1;
	return 0;
}
