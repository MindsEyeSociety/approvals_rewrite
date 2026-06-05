<?php
  include_once("db.inc");
  $db->query("select * from venues where venue=?", [$_POST["venue"]]);

	if ( $db->numRows() == 0 ) {
    $db->query("INSERT INTO venues (venue) VALUES (?)", [$_POST["venue"]]);
	}

  header("Location: ModifyVenueList1.php");
 ?>