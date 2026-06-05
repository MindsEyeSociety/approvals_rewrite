<?php
  include_once("db.inc");
	
	$modify = $_POST["modify"];
	
	if ( isset( $_POST["delete"] ) ) {
		$thisDelete = "(" . implode( ",", $_POST["delete"] ) . ")";
		$db->query("DELETE FROM venues WHERE id IN $thisDelete");
		$modifyInDelete = in_array( $modify, $_POST["delete"]);
	} 
	if( isset($_POST["modify"]) && !$modifyInDelete ) {
	  header("Location: ModifyVenueList3.php?modify=$_POST[modify]&");
  } else {
	  header("Location: ModifyVenueList1.php");
	}
  ?>
