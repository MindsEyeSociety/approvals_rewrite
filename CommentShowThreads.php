<?php
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
$base_url=$_SERVER['SCRIPT_NAME']."?".$_SERVER['QUERY_STRING'];
$base_url = str_replace( "showdetails=1&", "", $base_url );
$base_url = str_replace( "showrecentdetails=1&", "", $base_url );

$comment_rows = displayComments( $smarty, $app_info, 0, 0, $comments, $_SESSION["super_user"] );
$smarty->assign("comment_rows", $comment_rows );
$smarty->assign("app_info",$app_info);
$smarty->assign( "hide_details_url", $base_url );
$smarty->assign( "show_all_url", $base_url . "showdetails=1&" );
$smarty->assign( "show_recent_url", $base_url . "showrecentdetails=1&" );
$smarty->assign( "show_details",isset($_GET['showdetails']) && $_GET['showdetails'] == 1 );
$smarty->assign("show_recent_details", isset($_GET['showrecentdetails']) && $_GET['showrecentdetails'] == '1');
$smarty->display( "AppDetailsComments.html" );
