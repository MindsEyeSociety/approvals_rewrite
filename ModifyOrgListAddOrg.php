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
	trim($_POST["orgName"]),
	$_POST["email"],
	$email_is_google
);
$organization->domain = trim($_POST["domain"]);
// Active defaults to 1 via the Organization class constructor, no need to set from form

if( !$orgDAO->hasDuplicate($organization) ) {
	$orgDAO->insert( $organization );
}
// Keep the public map's Google Sheet in sync (best-effort; never blocks the save).
include_once("classes/GoogleSheetsService.php");
GoogleSheetsService::syncOrgMap();
include_once("session_setup.inc");
header("Location: ModifyOrgList1.php");
