<?php
include_once( "db.inc" );
// Process the form information then pass along the URL params
$char_id = isset($_POST['char_id'])?$_POST['char_id']:"";
$user_id = isset($_POST['user_id'])?$_POST['user_id']:"";

if ( is_numeric( $char_id ) ) {
	$characterDAO = $daoFactory->getCharacterDAO();
	$character = $characterDAO->readByID( $char_id );
	header("Location: AppDetails.php?char_id=$char_id&mode=add&appuser_id=$character->user_id&apporg_id=$character->org_id");
	exit();
} else {
	if ( $char_id=="AddNewChar" ) {
		header("Location: AddCharacter.php?redirect=AppDetails&appuser_id=$user_id&");
		exit();
	} else if ( $char_id=="NPC" ) {
		header("Location: AddNPC.php?redirect=AppDetails&");
		exit();
	} else if ( $char_id=="NonChar" ) {
		header("Location: AppDetails.php?char_id=NonChar&mode=add&");
		exit();
	} else if ( $char_id=="NotMyApplication" ) {
		header("Location: AddNewAppNotMyCharacter.php");
		exit();
	} else {
		header("Location: AddNewApp.php");
		exit();
	}
}

header("Location: AddNewApp.php");
