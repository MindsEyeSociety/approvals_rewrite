<?php
include_once("include/url.inc");
function displayComments( $smarty, $app_info, $comment_id, $depth, $comments, $is_super_user ) {
	$result = "";
	foreach( $comments as $comment ) {
	    if ( $comment['parent_comment_id'] == $comment_id ) {
	    	if( $is_super_user ||
				$comment['constraint_level'] == ''  ||
				( $app_info->user_id != $_SESSION['user_id'] &&
					$_SESSION['constraint_level'] == 'high' &&
					$_SESSION['max_final_authority_level'] == 'region' ) ||
					( $app_info->user_id != $_SESSION['user_id'] &&
						in_array( $comment['constraint_level'], $_SESSION['constraint_level_list'] ) ) ) {
				$result .= printComment( $smarty, $app_info, $comment, $depth);
				$result .= displayComments( $smarty, $app_info, $comment['id'], $depth+1, $comments, $is_super_user );
			}
		}
	}
	return $result;
}

/*function printComment( $smarty, $app_info, $comment, $depth ) {
	$show_recent_details = isset( $_GET['showrecentdetails'] );
	$show_details = isset( $_GET['showdetails'] );
	$session_seconds = $_SESSION['last_login_date'];
	if( $show_details ||
		( $show_recent_details && $comment['comment_date'] > $session_seconds ) ) {
		$comment["show_vote"] = ( $_SESSION['constraint_level'] == "high" ||
			$_SESSION['constraint_level'] == "top" ) &&
			$app_info->user_id != $_SESSION['user_id'] &&
			$comment['rating'] != 0;
		$smarty->assign("depth", $depth);
		$smarty->assign("comment",$comment);
		$smarty->assign("app_info", $app_info);
		return $smarty->fetch("fragments/commentFragment.html");
	}
	return "";
}*/
function printComment( $smarty, $app_info, $comment, $depth ) {
	$show_recent_details = isset( $_GET['showrecentdetails'] );
	$show_details = isset( $_GET['showdetails'] );
	$session_seconds = $_SESSION['last_login_date'];
	if(!( $show_details ||
		( $show_recent_details && $comment['comment_date'] > $session_seconds ) )) {
		$comment['comment'] = '';
	}
		$comment["show_vote"] = ( $_SESSION['constraint_level'] == "high" ||
			$_SESSION['constraint_level'] == "top" ) &&
			$app_info->user_id != $_SESSION['user_id'] &&
			$comment['rating'] != 0;
		$smarty->assign("depth", $depth);
		$smarty->assign("comment",$comment);
		$smarty->assign("app_info", $app_info);
		return $smarty->fetch("fragments/commentFragment.html");
	
	return "";
}
$commentDAO = $daoFactory->getCommentDAO();
$comments = $commentDAO->readCommentsByApp( $app_info->id );
/* Build the detail-toggle links from the current query string. Parsing and
   re-emitting (rather than concatenating) means the links are correct whether
   or not the page was reached by a link ending in "&". The id is re-set from
   $app_info rather than trusted from the URL, so a link that arrived malformed
   from an older page is repaired rather than propagated, and a render reached
   by POST (no query string at all) still links back to the right application. */
$script_name  = $_SERVER['SCRIPT_NAME'] ?? "AppDetails.php";
$query_string = $_SERVER['QUERY_STRING'] ?? "";
$base_query   = queryStringMerge( $query_string,
	array( "id" => $app_info->id ),
	array( "showdetails", "showrecentdetails" ) );

$comment_rows = displayComments( $smarty, $app_info, 0, 0, $comments, $_SESSION["super_user"] );
$smarty->assign("comment_rows", $comment_rows );
$smarty->assign("app_info",$app_info);
$smarty->assign( "hide_details_url", pageUrl( $script_name, $base_query ) );
$smarty->assign( "show_all_url",
	pageUrl( $script_name, queryStringMerge( $base_query, array( "showdetails" => 1 ) ) ) );
$smarty->assign( "show_recent_url",
	pageUrl( $script_name, queryStringMerge( $base_query, array( "showrecentdetails" => 1 ) ) ) );
$smarty->assign( "show_details",isset($_GET['showdetails']) && $_GET['showdetails'] == 1 );
$smarty->assign("show_recent_details", isset($_GET['showrecentdetails']) && $_GET['showrecentdetails'] == '1');
$smarty->display( "AppDetailsComments.html" );
