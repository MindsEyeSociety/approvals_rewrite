<?php
  include_once( "db.inc" );
	$db->query("Select * from venues where venue = ? and ID<>?", [$_POST["venue"], $_POST["id"]]);
  if ( $db->numRows() == 0 ) {
  	$query= "update venues set	venue=? where ID=?";
	$params = [
 		$_POST['venue'],
 		$_POST['id']
 	];
    $db->query($query, $params);
  }
  header("Location: ModifyVenueList1.php");
 ?>
