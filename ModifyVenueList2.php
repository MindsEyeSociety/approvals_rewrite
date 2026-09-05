<?php
  include_once("db.inc");
	
	$modify = $_POST["modify"];
	
	if ( isset( $_POST["delete"] ) ) {
		$placeholders = implode(",", array_fill(0, count($_POST["delete"]), "?"));
		$db->query("DELETE FROM venues WHERE id IN ($placeholders)", $_POST["delete"]);
		$modifyInDelete = in_array( $modify, $_POST["delete"]);
	} 
	if( isset($_POST["modify"]) && !$modifyInDelete ) {
	  header("Location: ModifyVenueList3.php?modify=$_POST[modify]&");
  } else {
	  header("Location: ModifyVenueList1.php");
	}
  ?>
