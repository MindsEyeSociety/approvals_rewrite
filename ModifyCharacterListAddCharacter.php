<?php
include_once("db.inc");
$characterDAO = $daoFactory->getCharacterDAO();

$character = new Character();
$character->name = $_POST["name"];
$character->venue_id = $_POST["venue"];
$character->subtype = $_POST["SubType"];
$character->active = 1;

if ( preg_match( "/NPC:(-?\d+)/", $_POST["Type"], $matches ) ) {
	$npc_id = $matches[1];
	$character->user_id = 0;
	$character->char_type = "NPC";
	if ( $npc_id > 0 ) {
		$character->org_id = null;
		$character->vss_id = $npc_id;
		$character->approved_in_vss = 1;
		$vss_npc = 1;
	} else {
		$character->org_id = $npc_id;
		$character->vss_id = -$npc_id;
		$character->approved_in_vss = 0;
		$org_npc = 1;
	}
	$thisuser_id=0;
} else {
	$character->user_id = $_POST["user_id"];
	$character->char_type = $_POST["Type"];
	$character->org_id = 0;
	$character->vss_id = 0;
	$character->approved_in_vss = 0;
	$thisuser_id=$_POST["user_id"];
}

$character->last_updated = time();
$character->background = $_POST["Background"];
$character->character_sheet = $_POST["character_sheet"];
$character->char_dead = 0;

if ( $_POST["name"]=="" ) {
	header ( "Location: AddCharacter.php?errmsg=2&" );
}

if( $characterDAO->create( $character ) ) {
	$max_id=$character->id;
	if ( $_POST["redirect"]=="AppDetails" ) {
		header( "Location: AppDetails.php?mode=add&char_id=$max_id&appUser_id=$thisuser_id&" );
	}	elseif( $vss_npc ) {
 		header("Location: ModifyVSSCharacterList1.php");
	} elseif ( $org_npc ) {
 		header("Location: app_main.php");
	} else {
 		header("Location: MoveCharacter.php?char_id=$max_id&redirect=$_POST[redirect]");
	}
} else {
	header( "Location: AddCharacter.php?errmsg=1&" );
}
