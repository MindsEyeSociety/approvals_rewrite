<?php

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