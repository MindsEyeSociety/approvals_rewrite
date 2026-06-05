<?php
  include_once("db.inc");

	$modify = $_POST["modify"];

	if ( $_POST["xptype"]=="earned" ) {
		$thistable="earnedxp";
	} else {
		$thistable="spentxp";
	}
	if ( !empty( $_POST["delete"] ) && empty( $_POST["modify"] )) {
		$thisDelete = "('" . implode( "','", $_POST["delete"] ) . "')";
		$db->query("DELETE FROM $thistable WHERE id IN $thisDelete and id!='$modify'");
	  header("Location: ModifyCharacterXPList1.php?character_id=$_POST[character_id]&");
		exit();
	} else if( !empty( $_POST['modify']) && is_numeric($_POST['modify']) ) {
	  header("Location: ModifyCharacterXPList3.php?modify=$_POST[modify]&table=$thistable&");
		exit();
  } else {
	  header("Location: ModifyCharacterXPList1.php?character_id=$_POST[character_id]&");
		exit();
	}
?>
