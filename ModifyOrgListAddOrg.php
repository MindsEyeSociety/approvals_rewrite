<?php

include_once("db.inc");
$orgDAO = $daoFactory->getOrganizationDAO();
$email_is_google = email_is_google($_POST["email"]);
$organization = new Organization(
	$_POST["globe"],
	$_POST["nation"],
	$_POST["region"],
	$_POST["domain"],
	$_POST["chapter"],
	$_POST["city"],
	$_POST["state"],
	$_POST["country"],
	$_POST["admin_user_id"],
	$_POST["orgName"],
	$_POST["email"],
	$email_is_google
);
// Active defaults to 1 via the Organization class constructor, no need to set from form

if( !$orgDAO->hasDuplicate($organization) ) {
	$orgDAO->insert( $organization );
}
include_once("session_setup.inc");
header("Location: ModifyOrgList1.php");
