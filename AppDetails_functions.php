<?php

/**
 * Whether a user is barred from giving FINAL approval (status "Approved") to an application.
 *
 * A user may never final-approve their own application, nor an application owned by a primary
 * storyteller they assist (the handbook conflict-of-interest rule). Applies to everyone, including
 * super users. When true, callers advance the application instead of finalizing it.
 *
 * @param $applicantUserId The application owner (applications.user_id).
 * @param $sessionUserId   The user attempting the approval ($_SESSION['user_id']).
 * @param $userInfoDAO     A UserInfoDAO, used only for the assistant-of-applicant lookup.
 * @return bool True if this user must not finalize this application.
 * @see UserInfoDAO::isAssistantToApplicant()
 *
 * Example:
 *   $blocked = finalApprovalBlocked( $app_info->user_id, $_SESSION['user_id'], $userInfoDAO );
 *   if( !$blocked ) { $ThisStatus = "Approved"; }
 */
function finalApprovalBlocked( $applicantUserId, $sessionUserId, $userInfoDAO ) {
	if( $applicantUserId == $sessionUserId ) {
		return true; // your own application
	}
	return $userInfoDAO->isAssistantToApplicant( $sessionUserId, $applicantUserId ); // your primary's
}

function addRevision( $app_id, $change ) {
	global $db;
	$query = "INSERT INTO revisions (application_id, user_id, revision_date, revision) VALUES (?, ?, now(), ?)";
	$db->query( $query, [$app_id, $_SESSION["user_id"], $change] );
}

function getPlayerOrgID( $user_id ) {
	global $db;
	$query = "SELECT org_id FROM users WHERE ID=?";
	$db->query($query, [$user_id]);
	$user_info = $db->nextRow();
	return $user_info["org_id"];
}

?>