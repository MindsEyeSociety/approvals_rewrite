<?php
  include_once( "db.inc" );

	
   if ( $_POST["table"]=="earnedxp" ) {
   	$datestamp = strtotime( stripslashes( $_POST["earneddate"] ) );
	$mysqldate = strftime( '%Y-%m-%d', $datestamp );
   	$query = "insert into earnedxp ".
		"(eventname, earneddate, xpearned, notes, character_id) ".
		"values (?, ?, ?, ?, ?)";
	$params = [
		$_POST["eventname"],
		$mysqldate,
		$_POST["xpearned"],
		$_POST["notes"],
		$_POST["character_id"]
	];
  } else {
  	$datestamp = strtotime( stripslashes( $_POST["spentdate"] ) );
		$mysqldate = strftime( '%Y-%m-%d', $datestamp );
  	$query = "insert into spentxp ".
		"(itembought, spentdate, xpspent, notes, character_id) ".
		"values (?, ?, ?, ?, ?)";
	$params = [
		$_POST["itembought"],
		$mysqldate,
		$_POST["xpspent"],
		$_POST["notes"],
		$_POST["character_id"]
	];
  }

  $db->query($query, $params);

  header("Location: ModifyCharacterXPList1.php?character_id=$_POST[character_id]&");
?>


